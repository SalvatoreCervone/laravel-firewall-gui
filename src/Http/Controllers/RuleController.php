<?php

namespace FirewallGui\LaravelFirewallGui\Http\Controllers;

use Closure;
use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use FirewallGui\LaravelFirewallGui\Support\IpMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use InvalidArgumentException;

class RuleController extends Controller
{
    public function __construct(
        protected FirewallManager $firewall,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['type', 'rule_type', 'group_id', 'search']);

        $query = FirewallRule::query()
            ->with('group')
            ->orderByDesc('priority')
            ->latest();

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['rule_type'])) {
            $query->where('rule_type', $filters['rule_type']);
        }

        if (! empty($filters['group_id'])) {
            $query->where('group_id', (int) $filters['group_id']);
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('value', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%");
            });
        }

        $perPage = (int) config('firewall-gui.gui.per_page', 20);

        return view('firewall-gui::rules.index', [
            'rules' => $query->paginate($perPage)->withQueryString(),
            'groups' => FirewallGroup::query()->orderByDesc('priority')->orderBy('name')->get(),
            'currentIp' => (string) $request->ip(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRule($request);
        [$ruleType, $normalizedValue] = IpMatcher::normalizeOrFail((string) $validated['value']);

        $groupId = isset($validated['group_id']) ? (int) $validated['group_id'] : null;
        $effectiveType = $this->resolveEffectiveType($groupId, $validated['type'] ?? null);
        $isActive = $request->boolean('is_active', true);
        $notifyOnHit = $effectiveType === FirewallRule::TYPE_NOTIFY
            || $request->boolean('notify_on_hit', false)
            || ($groupId !== null && (bool) FirewallGroup::query()->whereKey($groupId)->value('notify_on_hit'));

        if ($this->wouldLockOutCurrentIp($request, $isActive, $effectiveType, $normalizedValue, $ruleType)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['value' => 'Cannot create an active blacklist rule that blocks your current IP address.']);
        }

        FirewallRule::query()->create([
            'group_id' => $groupId,
            'type' => $effectiveType,
            'rule_type' => $ruleType,
            'value' => $normalizedValue,
            'label' => $validated['label'] ?? null,
            'is_active' => $isActive,
            'notify_on_hit' => $notifyOnHit,
            'priority' => (int) ($validated['priority'] ?? 0),
            'expires_at' => ! empty($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at']) : null,
            'created_by' => 'gui',
        ]);

        $this->firewall->clearCache();

        return redirect()
            ->back()
            ->with('success', "Rule [{$normalizedValue}] created.");
    }

    public function update(Request $request, FirewallRule $rule): RedirectResponse
    {
        $validated = $this->validateRule($request);
        [$ruleType, $normalizedValue] = IpMatcher::normalizeOrFail((string) $validated['value']);

        $groupId = isset($validated['group_id']) ? (int) $validated['group_id'] : null;
        $effectiveType = $this->resolveEffectiveType($groupId, $validated['type'] ?? null);
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : $rule->is_active;
        $notifyOnHit = $effectiveType === FirewallRule::TYPE_NOTIFY || $request->boolean('notify_on_hit', $rule->notify_on_hit);

        if ($this->wouldLockOutCurrentIp($request, $isActive, $effectiveType, $normalizedValue, $ruleType)) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['value' => 'Cannot save an active blacklist rule that blocks your current IP address.']);
        }

        $rule->update([
            'group_id' => $groupId,
            'type' => $effectiveType,
            'rule_type' => $ruleType,
            'value' => $normalizedValue,
            'label' => $validated['label'] ?? null,
            'is_active' => $isActive,
            'notify_on_hit' => $notifyOnHit,
            'priority' => (int) ($validated['priority'] ?? 0),
            'expires_at' => ! empty($validated['expires_at']) ? Carbon::parse((string) $validated['expires_at']) : null,
        ]);

        $this->firewall->clearCache();

        return redirect()
            ->back()
            ->with('success', "Rule [{$rule->value}] updated.");
    }

    public function toggle(Request $request, FirewallRule $rule): RedirectResponse
    {
        $newState = ! $rule->is_active;
        $effectiveType = $rule->group?->type ?? $rule->type;

        if ($this->wouldLockOutCurrentIp($request, $newState, $effectiveType, $rule->value, $rule->rule_type)) {
            return redirect()
                ->back()
                ->with('error', 'Cannot activate a blacklist rule that blocks your current IP address.');
        }

        $rule->is_active = $newState;
        $rule->save();

        $this->firewall->clearCache();

        $status = $newState ? 'activated' : 'deactivated';

        return redirect()
            ->back()
            ->with('success', "Rule [{$rule->value}] {$status}.");
    }

    public function destroy(FirewallRule $rule): RedirectResponse
    {
        $value = $rule->value;
        $rule->delete();

        $this->firewall->clearCache();

        return redirect()
            ->back()
            ->with('success', "Rule [{$value}] deleted.");
    }

    public function bulkImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entries' => ['required', 'string'],
            'type' => ['required_without:group_id', 'nullable', 'in:whitelist,blacklist,notify'],
            'group_id' => ['nullable', 'integer', 'exists:firewall_groups,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'notify_on_hit' => ['nullable', 'boolean'],
        ]);

        $groupId = isset($validated['group_id']) ? (int) $validated['group_id'] : null;
        $effectiveType = $this->resolveEffectiveType($groupId, $validated['type'] ?? null);
        $label = $validated['label'] ?? null;
        $notifyOnHit = $effectiveType === FirewallRule::TYPE_NOTIFY
            || $request->boolean('notify_on_hit', false)
            || ($groupId !== null && (bool) FirewallGroup::query()->whereKey($groupId)->value('notify_on_hit'));

        $lines = preg_split('/[\r\n,]+/', (string) $validated['entries']) ?: [];

        $created = 0;
        $skippedInvalid = 0;
        $skippedLockout = 0;

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            try {
                [$ruleType, $normalizedValue] = IpMatcher::normalizeOrFail($line);
            } catch (InvalidArgumentException) {
                $skippedInvalid++;

                continue;
            }

            if ($this->wouldLockOutCurrentIp($request, true, $effectiveType, $normalizedValue, $ruleType)) {
                $skippedLockout++;

                continue;
            }

            FirewallRule::query()->create([
                'group_id' => $groupId,
                'type' => $effectiveType,
                'rule_type' => $ruleType,
                'value' => $normalizedValue,
                'label' => $label,
                'is_active' => true,
                'notify_on_hit' => $notifyOnHit,
                'created_by' => 'gui-bulk',
            ]);

            $created++;
        }

        $this->firewall->clearCache();

        $redirect = redirect()
            ->back()
            ->with('success', "Imported {$created} rule(s).");

        $warnings = [];
        if ($skippedInvalid > 0) {
            $warnings[] = "{$skippedInvalid} invalid entry/entries skipped";
        }
        if ($skippedLockout > 0) {
            $warnings[] = "{$skippedLockout} entry/entries skipped to prevent self-lockout";
        }

        if ($warnings !== []) {
            $redirect->with('warning', ucfirst(implode('; ', $warnings)).'.');
        }

        return $redirect;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateRule(Request $request): array
    {
        return $request->validate([
            'value' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! IpMatcher::isValid((string) $value)) {
                        $fail('The value must be a valid IP address, CIDR block, or IP range.');
                    }
                },
            ],
            'type' => ['required_without:group_id', 'nullable', 'in:whitelist,blacklist,notify'],
            'group_id' => ['nullable', 'integer', 'exists:firewall_groups,id'],
            'label' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'notify_on_hit' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer'],
        ]);
    }

    protected function resolveEffectiveType(?int $groupId, ?string $type): string
    {
        if (is_string($type) && $type !== '') {
            return $type;
        }

        if ($groupId !== null) {
            $groupType = FirewallGroup::query()->findOrFail($groupId)->type;

            return $groupType === 'mixed' ? FirewallRule::TYPE_BLACKLIST : $groupType;
        }

        return FirewallRule::TYPE_BLACKLIST;
    }

    protected function wouldLockOutCurrentIp(
        Request $request,
        bool $isActive,
        string $effectiveType,
        string $normalizedValue,
        string $ruleType,
    ): bool {
        if (! (bool) config('firewall-gui.gui.prevent_self_lockout', true)) {
            return false;
        }

        if (! $isActive || $effectiveType !== FirewallRule::TYPE_BLACKLIST) {
            return false;
        }

        return IpMatcher::matches((string) $request->ip(), $normalizedValue, $ruleType);
    }
}
