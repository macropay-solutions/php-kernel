<?php

namespace MacropaySolutions\Kernel\Tests\Fixtures\Mail;

use MacropaySolutions\Kernel\Mail\Mailable;

class OrderShippedMail extends Mailable
{
    public function __construct(public int $orderId = 0)
    {
    }

    public function build(): static
    {
        return $this->subject('Order #' . $this->orderId . ' shipped')
            ->html('<p>Your order <b>#' . $this->orderId . '</b> is on its way.</p>')
            ->attachData('id=' . $this->orderId, 'order.txt', ['mime' => 'text/plain']);
    }
}
