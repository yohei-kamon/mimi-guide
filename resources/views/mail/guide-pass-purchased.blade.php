<x-mail::message>
# 音声ガイドのご購入ありがとうございます

{{ $museum->name() }}の音声ガイドをご購入いただきました。

- 金額: {{ number_format($guidePass->amount) }}円
- 有効期限: {{ $guidePass->expiresAtLabel() }}

有効期限まで、この館の全ての展示で解説と音声をご利用いただけます。
ブラウザを閉じた場合や別の端末で聴き直すときは、次のボタンからパスを開いてください。

<x-mail::button :url="$passUrl">
パスを開く
</x-mail::button>

このボタンの URL を知っている人は誰でもパスを使えます。他の人に共有しないでください。

[{{ $museum->name() }}のトップページ]({{ $museumUrl }})

{{ config('app.name') }}
</x-mail::message>
