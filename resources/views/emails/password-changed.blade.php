<p>Hello {{ $user->primaryProfile->first_name ?? $user->email }},</p>

<p>An administrator ({{ $admin->email }}) has reset your account password.</p>

<p><strong>New password:</strong> <code>{{ $plainPassword }}</code></p>

<p>For security, please log in and change it immediately.</p>

<p>This temporary password expires on {{ $expires->format('d M Y H:i') }}.</p>

<p>— {{ config('app.name') }} Team</p>