<?php

namespace MacropaySolutions\Kernel\Tests\Notifications;

use MacropaySolutions\Kernel\Broadcasting\Broadcasters\NullBroadcaster;
use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\ShouldQueue;
use MacropaySolutions\Kernel\Notifications\ChannelManager;
use MacropaySolutions\Kernel\Notifications\Notification;
use MacropaySolutions\Kernel\Notifications\NotificationSender;
use MacropaySolutions\Kernel\Pagination\LengthAwarePaginator;
use MacropaySolutions\Kernel\Pagination\Paginator;
use PHPUnit\Framework\TestCase;

class QueueFallbackLocaleAndPaginationTest extends TestCase
{
    protected function tearDown(): void
    {
        Paginator::currentPageResolver(static fn() => 1);
    }

    public function testAChannelMissingFromViaConnectionsKeepsTheNotificationConnection(): void
    {
        $bus = new QueueFallbackLocaleAndPaginationTestBus();
        $sender = new NotificationSender(null, $bus, null);

        (new \ReflectionMethod($sender, 'queueNotification'))->invoke(
            $sender,
            [new QueueFallbackLocaleAndPaginationTestNotifiable()],
            new QueueFallbackLocaleAndPaginationTestNotification()
        );

        $this->assertSame(['mail' => ['sqs', 'mails'], 'database' => ['redis', 'default-queue']], $bus->dispatched);
    }

    public function testTheLocaleIsForTheNextSendOnly(): void
    {
        $manager = (new \ReflectionClass(ChannelManager::class))->newInstanceWithoutConstructor();
        $manager->locale('ro');
        $pull = new \ReflectionMethod($manager, 'pullLocale');

        $this->assertSame('ro', $pull->invoke($manager));
        $this->assertNull($pull->invoke($manager));
    }

    public function testChannelPatternDotsAreLiteral(): void
    {
        $broadcaster = (new \ReflectionClass(NullBroadcaster::class))->newInstanceWithoutConstructor();
        $matches = new \ReflectionMethod($broadcaster, 'channelNameMatchesPattern');

        $this->assertSame(0, $matches->invoke($broadcaster, 'ordersX5', 'orders.{id}'));
        $this->assertSame(1, $matches->invoke($broadcaster, 'orders.5', 'orders.{id}'));
    }

    public function testPaginatorPageNameAndZeroPerPage(): void
    {
        Paginator::currentPageResolver(static fn(string $name = 'page') => 'p' === $name ? 3 : 1);

        $this->assertSame(3, (new Paginator([], 10, null, ['pageName' => 'p']))->currentPage());
        $this->assertSame(5, (new LengthAwarePaginator([1], 5, 0))->lastPage());
    }
}

class QueueFallbackLocaleAndPaginationTestBus
{
    public array $dispatched = [];

    public function dispatch($job): void
    {
        $this->dispatched[$job->channels[0]] = [$job->connection, $job->queue];
    }
}

class QueueFallbackLocaleAndPaginationTestNotifiable
{
}

class QueueFallbackLocaleAndPaginationTestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onConnection('redis')->onQueue('default-queue');
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function viaConnections(): array
    {
        return ['mail' => 'sqs'];
    }

    public function viaQueues(): array
    {
        return ['mail' => 'mails'];
    }
}
