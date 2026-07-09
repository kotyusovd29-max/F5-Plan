@php
    use App\Models\ProjectAiMessage;
@endphp

<article
    class="ai-chat-message ai-chat-message--{{ $message->role }}"
    data-ai-message-id="{{ $message->id }}"
>
    <header class="ai-chat-message__meta">
        <span class="ai-chat-message__role">
            {{ $message->role === ProjectAiMessage::ROLE_USER ? 'Вы' : 'ИИ' }}
        </span>
        <time class="ai-chat-message__time">{{ $message->created_at?->format('d.m.Y H:i') }}</time>
    </header>

    <div class="ai-chat-message__content">{!! nl2br(e($message->content)) !!}</div>

    @if ($message->role === ProjectAiMessage::ROLE_ASSISTANT && is_array($message->proposalChanges()))
        <div class="ai-chat-message__changes">
            @include('projects.ai._changes_preview', ['changes' => $message->proposalChanges()])

            @if ($message->hasPendingChanges())
                <button
                    type="button"
                    class="btn btn--primary btn--sm ai-chat-message__apply"
                    data-ai-apply-message="{{ $message->id }}"
                    data-apply-url="{{ route('projects.ai.chat.apply', [$project, $message]) }}"
                >
                    Применить изменения
                </button>
            @elseif ($message->applied_at)
                <p class="ai-chat-message__applied">Изменения применены {{ $message->applied_at->format('d.m.Y H:i') }}</p>
            @endif
        </div>
    @endif
</article>
