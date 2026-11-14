<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Firewall Access Alert</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #0f172a; padding: 24px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
        <div style="background: #0f172a; color: #ffffff; padding: 18px 24px;">
            <h2 style="margin: 0; font-size: 18px;">
                Firewall {{ $decisionAction === 'blocked' ? 'Blocked' : 'Access' }} Notification
            </h2>
            <p style="margin: 4px 0 0; font-size: 13px; color: #94a3b8;">
                First {{ $decisionAction === 'blocked' ? 'blocked attempt' : 'access' }} of the day detected for a watched IP / range.
            </p>
        </div>

        <div style="padding: 24px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="padding: 8px 0; color: #64748b; width: 140px;">Action</td>
                    <td style="padding: 8px 0; font-weight: 600; text-transform: uppercase;">{{ $decisionAction }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b; width: 140px;">IP Address</td>
                    <td style="padding: 8px 0; font-family: monospace; font-weight: 600;">{{ $ipAddress }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b;">Request</td>
                    <td style="padding: 8px 0; font-family: monospace;">{{ $method }} {{ $path }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b;">Timestamp</td>
                    <td style="padding: 8px 0;">{{ $accessedAt }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #64748b;">Matched Rule</td>
                    <td style="padding: 8px 0; font-family: monospace;">
                        {{ $matchedRule['value'] ?? 'N/A' }}
                        @if (!empty($matchedRule['label']))
                            ({{ $matchedRule['label'] }})
                        @endif
                    </td>
                </tr>
                @if ($matchedGroup)
                    <tr>
                        <td style="padding: 8px 0; color: #64748b;">Group</td>
                        <td style="padding: 8px 0;">{{ $matchedGroup['name'] }}</td>
                    </tr>
                @endif
                @if ($userAgent)
                    <tr>
                        <td style="padding: 8px 0; color: #64748b; vertical-align: top;">User Agent</td>
                        <td style="padding: 8px 0; color: #475569; font-size: 12px;">{{ $userAgent }}</td>
                    </tr>
                @endif
            </table>
        </div>
    </div>
</body>
</html>
