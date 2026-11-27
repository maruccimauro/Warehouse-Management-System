<?php

use App\Modules\Identity\Actions\SyncRolesAndPermissions;
use App\Modules\Identity\Authorization\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inbox\Contracts\Inbox;
use App\Modules\Inbox\Models\InboxMessage;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/*
| Bandeja de entrada (E3.27): envío desde los módulos, listado propio,
| contador de no leídos, marcas en lote y limpieza de archivados.
*/

/**
 * @param  array<string, scalar|null>  $parameters
 */
function sendToInbox(User $recipient, string $type = 'test.notice', array $parameters = []): InboxMessage
{
    app(Inbox::class)->send([$recipient->id], $type, $parameters);

    return InboxMessage::query()->where('recipient_id', $recipient->id)->orderByDesc('id')->firstOrFail();
}

function markInbox(InboxMessage $message, ?string $readAt, ?string $archivedAt = null): void
{
    DB::table('inbox_messages')->where('id', $message->id)->update(['read_at' => $readAt, 'archived_at' => $archivedAt]);
}

beforeEach(function () {
    app(SyncRolesAndPermissions::class)();
    $this->user = userWithAccess(Role::Operator);
    $this->other = userWithAccess(Role::Operator);
});

describe('sending', function () {
    it('stores one message per recipient without repeating, with its parameters and subject', function () {
        app(Inbox::class)->send([$this->user->id, $this->other->id, $this->user->id], 'user.critical_role_assigned', ['role' => 'administrator'], $this->other);

        $messages = InboxMessage::query()->orderBy('id')->get();

        expect($messages)->toHaveCount(2)
            ->and($messages->pluck('recipient_id')->all())->toBe([$this->user->id, $this->other->id])
            ->and($messages->first()?->parameters)->toBe(['role' => 'administrator'])
            ->and($messages->first()?->subject_type)->toBe('user')
            ->and($messages->first()?->subject_id)->toBe($this->other->id)
            ->and($messages->first()?->read_at)->toBeNull();
    });

    it('does nothing without recipients', function () {
        app(Inbox::class)->send([], 'test.notice');

        expect(InboxMessage::query()->count())->toBe(0);
    });

    it('is discarded with the transaction of the change that sends it', function () {
        try {
            DB::transaction(function () {
                app(Inbox::class)->send([$this->user->id], 'test.notice');

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        expect(InboxMessage::query()->count())->toBe(0);
    });
});

describe('listing', function () {
    it('lists only the own messages, newest first, with the parameters as an object', function () {
        $first = sendToInbox($this->user, 'test.first');
        $second = sendToInbox($this->user, 'test.second', ['count' => 3]);
        sendToInbox($this->other);

        $this->actingAs($this->user)
            ->getJson('/api/v1/me/inbox')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.type', 'test.second')
            ->assertJsonPath('data.0.parameters.count', 3)
            ->assertJsonPath('data.0.subject', null)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('meta.total', 2);
    });

    it('separates the inbox folder from the archived one and filters the unread', function () {
        $unread = sendToInbox($this->user);
        $read = sendToInbox($this->user);
        $archived = sendToInbox($this->user);
        markInbox($read, now()->toJSON());
        markInbox($archived, now()->toJSON(), now()->toJSON());

        $ids = fn (string $query): array => collect($this->actingAs($this->user)->getJson("/api/v1/me/inbox?{$query}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        expect($ids('filter[folder]=inbox'))->toBe([$unread->id, $read->id])
            ->and($ids('filter[folder]=archived'))->toBe([$archived->id])
            ->and($ids('filter[folder]=inbox&filter[unread]=1'))->toBe([$unread->id]);
    });

    it('rejects an unknown folder', function () {
        $this->actingAs($this->user)
            ->getJson('/api/v1/me/inbox?filter[folder]=trash')
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    });

    it('counts the unread messages of the inbox folder', function () {
        sendToInbox($this->user);
        markInbox(sendToInbox($this->user), now()->toJSON());
        markInbox(sendToInbox($this->user), now()->toJSON(), now()->toJSON());
        sendToInbox($this->other);

        $this->actingAs($this->user)
            ->getJson('/api/v1/me/inbox/unread-count')
            ->assertOk()
            ->assertExactJson(['data' => ['unread' => 1]]);
    });

    it('requires a session', function (string $uri) {
        $this->getJson($uri)->assertUnauthorized();
    })->with(['/api/v1/me/inbox', '/api/v1/me/inbox/unread-count']);
});

describe('marking', function () {
    it('marks several messages as read and back as unread', function () {
        $a = sendToInbox($this->user);
        $b = sendToInbox($this->user);

        $this->actingAs($this->user)->patchJson('/api/v1/me/inbox', ['ids' => [$a->id, $b->id], 'read' => true])->assertNoContent();

        expect($a->fresh()?->read_at)->not->toBeNull()
            ->and($b->fresh()?->read_at)->not->toBeNull();

        $this->actingAs($this->user)->patchJson('/api/v1/me/inbox', ['ids' => [$a->id], 'read' => false])->assertNoContent();

        expect($a->fresh()?->read_at)->toBeNull()
            ->and($b->fresh()?->read_at)->not->toBeNull();
    });

    it('keeps the first reading time when marking read again', function () {
        $message = sendToInbox($this->user);
        markInbox($message, now()->subDay()->toJSON());
        $readAt = $message->fresh()?->read_at?->toJSON();

        $this->actingAs($this->user)->patchJson('/api/v1/me/inbox', ['ids' => [$message->id], 'read' => true])->assertNoContent();

        expect($message->fresh()?->read_at?->toJSON())->toBe($readAt);
    });

    it('archives marking as read and restores to the inbox as read', function () {
        $message = sendToInbox($this->user);

        $this->actingAs($this->user)->patchJson('/api/v1/me/inbox', ['ids' => [$message->id], 'archived' => true])->assertNoContent();

        expect($message->fresh()?->archived_at)->not->toBeNull()
            ->and($message->fresh()?->read_at)->not->toBeNull();

        $this->actingAs($this->user)->patchJson('/api/v1/me/inbox', ['ids' => [$message->id], 'archived' => false])->assertNoContent();

        expect($message->fresh()?->archived_at)->toBeNull()
            ->and($message->fresh()?->read_at)->not->toBeNull();
    });

    it('returns an archived message to the inbox when marking it unread', function () {
        $message = sendToInbox($this->user);
        markInbox($message, now()->toJSON(), now()->toJSON());

        $this->actingAs($this->user)->patchJson('/api/v1/me/inbox', ['ids' => [$message->id], 'read' => false])->assertNoContent();

        expect($message->fresh()?->archived_at)->toBeNull()
            ->and($message->fresh()?->read_at)->toBeNull();
    });

    it('rejects a message of another user without changing anything', function () {
        $own = sendToInbox($this->user);
        $foreign = sendToInbox($this->other);

        $this->actingAs($this->user)
            ->patchJson('/api/v1/me/inbox', ['ids' => [$own->id, $foreign->id], 'read' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.1'], 'errors');

        expect($own->fresh()?->read_at)->toBeNull()
            ->and($foreign->fresh()?->read_at)->toBeNull();
    });

    it('validates the body', function (array $body, string $field) {
        $this->actingAs($this->user)
            ->patchJson('/api/v1/me/inbox', $body)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    })->with([
        'without ids' => [['read' => true], 'ids'],
        'empty ids' => [['ids' => [], 'read' => true], 'ids'],
        'without a mark' => [['ids' => [1]], 'read'],
        'mark not boolean' => [['ids' => [1], 'archived' => 'yes'], 'archived'],
        'too many ids' => [['ids' => range(1, 201), 'read' => true], 'ids'],
    ]);
});

describe('pruning', function () {
    it('deletes only the messages archived longer than the retention period', function () {
        $old = sendToInbox($this->user);
        $recent = sendToInbox($this->user);
        $oldInInbox = sendToInbox($this->user);
        markInbox($old, now()->subDays(91)->toJSON(), now()->subDays(91)->toJSON());
        markInbox($recent, now()->subDays(89)->toJSON(), now()->subDays(89)->toJSON());
        DB::table('inbox_messages')->where('id', $oldInInbox->id)->update(['created_at' => now()->subYear()]);

        $this->artisan('wms:prune-inbox')->assertSuccessful();

        expect(InboxMessage::query()->orderBy('id')->pluck('id')->all())->toBe([$recent->id, $oldInInbox->id]);
    });

    it('is scheduled daily', function () {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'wms:prune-inbox'));

        expect($events)->toHaveCount(1)
            ->and($events->first()?->expression)->toBe('0 0 * * *');
    });
});
