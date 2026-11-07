<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Broadcasting;

use MacropaySolutions\Kernel\Broadcasting\Channel;
use MacropaySolutions\Kernel\Broadcasting\PrivateChannel;
use MacropaySolutions\Kernel\Contracts\Broadcasting\ShouldBroadcast;

class OrderUpdated implements ShouldBroadcast
{
    public function __construct(public int $orderId, public string $status, public bool $send = true)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('orders'), new PrivateChannel('user.' . $this->orderId)];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->orderId, 'status' => $this->status];
    }

    public function broadcastWhen(): bool
    {
        return $this->send;
    }
}
