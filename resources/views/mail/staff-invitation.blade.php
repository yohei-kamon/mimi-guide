<x-mail::message>
# 館管理者アカウントのご案内

{{ $user->name }} 様

{{ $museumName }}の館管理者として、{{ config('app.name') }}のアカウントを発行しました。
次のボタンからパスワードを設定すると、ログインできるようになります。

- ログイン用メールアドレス: {{ $user->email }}

<x-mail::button :url="$setPasswordUrl">
パスワードを設定する
</x-mail::button>

このボタンの有効期限は {{ $expireMinutes }} 分です。期限が切れた場合は、ログイン画面の「パスワードをお忘れですか」から同じメールアドレスで設定し直してください。

{{ config('app.name') }}
</x-mail::message>
