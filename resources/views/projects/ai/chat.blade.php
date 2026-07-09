@extends('layouts.app')

@section('title', 'ИИ-чат — ' . $project->company_name)

@section('content')
    <div class="project-page project-page--chat">
        <div class="page-header">
            <div>
                <h2 class="page-header__title">{{ $project->company_name }}</h2>
                <p class="page-header__subtitle">
                    <a href="{{ route('home') }}">← К проектам</a>
                    · Автор: {{ $project->user->name }}
                </p>
            </div>
            <div class="page-header__actions">
                <a href="{{ route('projects.export.docx', $project) }}" class="btn btn--primary">
                    Сохранить в DOCX
                </a>
            </div>
        </div>

        @include('projects._project_nav', ['project' => $project, 'current' => 'ai'])

        <div
            class="ai-chat"
            id="project-ai-chat"
            data-send-url="{{ route('projects.ai.chat.send', $project) }}"
            data-apply-url-template="{{ route('projects.ai.chat.apply', [$project, '__MESSAGE__']) }}"
        >
            <div class="ai-chat__messages" data-ai-chat-messages>
                @forelse ($messages as $message)
                    @include('projects.ai._message', ['message' => $message, 'project' => $project])
                @empty
                    <div class="ai-chat__empty" data-ai-chat-empty>
                        <h3 class="ai-chat__empty-title">Чат с ИИ</h3>
                        <p>Опишите задачу или вставьте полный бриф внедрения amoCRM — ИИ подготовит кейс и настройки проекта.</p>
                        <ul class="ai-chat__hints">
                            <li>Короткий запрос: «Добавь поле „Источник“ в контакты»</li>
                            <li>Большой бриф: анализ бизнеса, воронки, поля, автоматизация, интеграции F5</li>
                            <li>После ответа нажмите «Применить изменения» для загрузки в проект</li>
                        </ul>
                    </div>
                @endforelse
            </div>

            <form class="ai-chat__composer" data-ai-chat-form>
                <textarea
                    name="message"
                    class="ai-chat__input form__textarea"
                    rows="8"
                    placeholder="Короткий запрос или полный бриф внедрения (можно большой текст)..."
                    required
                ></textarea>
                <div class="ai-chat__composer-actions">
                    <p class="ai-chat__composer-hint">Большие брифы обрабатываются 2–4 минуты. Изменения применяются только после вашего подтверждения.</p>
                    <button type="submit" class="btn btn--primary" data-ai-chat-send>Отправить</button>
                </div>
            </form>

            <div class="ai-chat__loading is-hidden" data-ai-chat-loading>
                <div class="ai-panel__spinner" aria-hidden="true"></div>
                <p data-ai-chat-loading-text>ИИ обрабатывает запрос…</p>
                <p class="ai-panel__loading-hint">Большие кейсы внедрения могут занять до 4 минут</p>
            </div>

            <div class="ai-chat__status is-hidden" data-ai-chat-status role="status"></div>

            @php
                $pendingApplyMessage = $messages->reverse()->first(
                    fn ($item) => $item->role === \App\Models\ProjectAiMessage::ROLE_ASSISTANT && $item->hasPendingChanges()
                );
            @endphp

            @if ($pendingApplyMessage)
                <div class="ai-chat__apply-bar" data-ai-apply-bar>
                    <p class="ai-chat__apply-bar-text">Настройки проекта готовы к загрузке</p>
                    <button
                        type="button"
                        class="btn btn--primary ai-chat__apply-bar-btn"
                        data-ai-apply-message="{{ $pendingApplyMessage->id }}"
                        data-apply-url="{{ route('projects.ai.chat.apply', [$project, $pendingApplyMessage]) }}"
                    >
                        Применить изменения
                    </button>
                </div>
            @endif
        </div>
    </div>

    @vite(['resources/js/project-ai-chat.js'])
@endsection
