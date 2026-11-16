<?php

namespace FirewallGui\LaravelFirewallGui\Http\Controllers;

use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Models\FirewallSetting;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use FirewallGui\LaravelFirewallGui\Support\IpMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function __construct(
        protected FirewallManager $firewall,
    ) {}

    public function index(Request $request): View
    {
        $perPage = (int) config('firewall-gui.gui.per_page', 20);

        $groups = FirewallGroup::query()
            ->withCount('rules')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate($perPage);

        return view('firewall-gui::groups.index', [
            'groups' => $groups,
            'globalCooldownHours' => $this->firewall->getNotificationSettings()['cooldown_hours'] ?? 24,
            'defaultRule' => $this->firewall->getDefaultRuleSettings(),
            'currentIp' => (string) $request->ip(),
        ]);
    }

    public function updateDefaultRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_action' => ['required', 'in:allow,block'],
            'default_notify' => ['nullable', 'boolean'],
        ]);

        FirewallSetting::query()->updateOrCreate(
            ['key' => 'firewall.default_action'],
            ['value' => $validated['default_action']]
        );

        FirewallSetting::query()->updateOrCreate(
            ['key' => 'firewall.default_notify'],
            ['value' => $request->boolean('default_notify') ? '1' : '0']
        );

        $this->firewall->clearCache();

        return redirect()
            ->back()
            ->with('success', __('firewall-gui::ui.groups.default_rule_saved'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:firewall_groups,name'],
            'type' => ['required', 'in:whitelist,blacklist,notify'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'notify_on_hit' => ['nullable', 'boolean'],
            'notification_cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'priority' => ['nullable', 'integer'],
        ]);

        $group = FirewallGroup::query()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'notify_on_hit' => $validated['type'] === FirewallRule::TYPE_NOTIFY || $request->boolean('notify_on_hit', false),
            'notification_cooldown_hours' => isset($validated['notification_cooldown_hours']) && $validated['notification_cooldown_hours'] !== ''
                ? (int) $validated['notification_cooldown_hours']
                : null,
            'priority' => (int) ($validated['priority'] ?? 0),
        ]);

        $this->firewall->clearCache();

        return redirect()
            ->back()
            ->with('success', "Group [{$group->name}] created.");
    }

    public function show(Request $request, FirewallGroup $group): View
    {
        $perPage = (int) config('firewall-gui.gui.per_page', 20);

        $rules = $group->rules()
            ->latest()
            ->paginate($perPage);

        return view('firewall-gui::groups.show', [
            'group' => $group,
            'rules' => $rules,
            'globalCooldownHours' => $this->firewall->getNotificationSettings()['cooldown_hours'] ?? 24,
            'currentIp' => (string) $request->ip(),
        ]);
    }

    public function update(Request $request, FirewallGroup $group): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('firewall_groups', 'name')->ignore($group->id)],
            'type' => ['required', 'in:whitelist,blacklist,notify'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'notify_on_hit' => ['nullable', 'boolean'],
            'notification_cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'priority' => ['nullable', 'integer'],
        ]);

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : $group->is_active;
        $notifyOnHit = $validated['type'] === FirewallRule::TYPE_NOTIFY || $request->boolean('notify_on_hit', $group->notify_on_hit);

        if ($isActive && $validated['type'] === FirewallRule::TYPE_BLACKLIST && $this->groupContainsCurrentIp($request, $group)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Cannot save an active blacklist group that contains a rule matching your current IP address.');
        }

        $typeChanged = $group->type !== $validated['type'];

        $group->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'is_active' => $isActive,
            'notify_on_hit' => $notifyOnHit,
            'notification_cooldown_hours' => array_key_exists('notification_cooldown_hours', $validated)
                ? (($validated['notification_cooldown_hours'] !== null && $validated['notification_cooldown_hours'] !== '')
                    ? (int) $validated['notification_cooldown_hours']
                    : null)
                : $group->notification_cooldown_hours,
            'priority' => (int) ($validated['priority'] ?? 0),
        ]);

        if ($typeChanged) {
            $group->rules()->update(['type' => $group->type]);
        }

        $this->firewall->clearCache();

        return redirect()
            ->back()
            ->with('success', "Group [{$group->name}] updated.");
    }

    public function toggle(Request $request, FirewallGroup $group): RedirectResponse
    {
        $newState = ! $group->is_active;

        if ($newState && $group->type === FirewallRule::TYPE_BLACKLIST && $this->groupContainsCurrentIp($request, $group)) {
            return redirect()
                ->back()
                ->with('error', 'Cannot activate a blacklist group that contains an active rule matching your current IP address.');
        }

        $group->is_active = $newState;
        $group->save();

        $this->firewall->clearCache();

        $status = $newState ? 'activated' : 'deactivated';

        return redirect()
            ->back()
            ->with('success', "Group [{$group->name}] {$status}.");
    }

    public function destroy(FirewallGroup $group): RedirectResponse
    {
        $name = $group->name;
        $group->delete();

        $this->firewall->clearCache();

        return redirect()
            ->route('firewall-gui.groups.index')
            ->with('success', "Group [{$name}] deleted.");
    }

    protected function groupContainsCurrentIp(Request $request, FirewallGroup $group): bool
    {
        if (! (bool) config('firewall-gui.gui.prevent_self_lockout', true)) {
            return false;
        }

        $currentIp = (string) $request->ip();

        foreach ($group->rules()->active()->get() as $rule) {
            if (IpMatcher::matches($currentIp, $rule->value, $rule->rule_type)) {
                return true;
            }
        }

        return false;
    }
}
