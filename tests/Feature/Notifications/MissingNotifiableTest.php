<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Notifications;

use MacropaySolutions\Kernel\Contracts\Queue\Job;
use MacropaySolutions\Kernel\Database\Obvious\Model;
use MacropaySolutions\Kernel\Database\Obvious\ModelNotFoundException;
use MacropaySolutions\Kernel\Database\Schema\Blueprint;
use MacropaySolutions\Kernel\Notifications\Notification;
use MacropaySolutions\Kernel\Notifications\SendQueuedNotifications;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Database\Author;

/**
 * A queued notification runs inside SendQueuedNotifications, so the notification's own $deleteWhenMissingModels
 * was never read: the job failed instead of being deleted when the notifiable was gone.
 */
class MissingNotifiableTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unsetConnectionResolver();

        $this->db()->getSchemaBuilder()->create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->integer('rating')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    public function test_the_job_is_deleted_when_the_notification_allows_it(): void
    {
        $job = $this->createMock(Job::class);
        $job->expects($this->once())->method('delete');

        SendQueuedNotifications::executeStorable(
            [['type' => 'model', 'class' => Author::class, 'id' => 999]],
            DeleteWhenMissingNotification::class,
            [],
            null,
            $job
        );
    }

    public function test_the_job_fails_when_the_notification_does_not_allow_it(): void
    {
        $this->expectException(ModelNotFoundException::class);

        SendQueuedNotifications::executeStorable(
            [['type' => 'model', 'class' => Author::class, 'id' => 999]],
            KeepWhenMissingNotification::class,
            [],
            null,
            $this->createStub(Job::class)
        );
    }
}

class DeleteWhenMissingNotification extends Notification
{
    public $deleteWhenMissingModels = true;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}

class KeepWhenMissingNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
