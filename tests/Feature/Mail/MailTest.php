<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Mail;

use MacropaySolutions\Kernel\Contracts\Mail\Factory as MailFactory;
use MacropaySolutions\Kernel\Mail\MailServiceProvider;
use MacropaySolutions\Kernel\Mail\Transport\ArrayTransport;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Tests\Fixtures\Mail\OrderShippedMail;
use MacropaySolutions\Kernel\Tests\Fixtures\Mail\QueuedOrderShippedMail;
use Symfony\Component\Mime\Email;

/**
 * Mailables sent through the real mail manager with the array transport, directly and through the queue.
 * The mailer is resolved before the queue was ever used, as in a request that sends mail first.
 */
class MailTest extends ApplicationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('config')->set('mail', [
            'default' => 'array',
            'mailers' => ['array' => ['transport' => 'array']],
            'from' => ['address' => 'shop@example.com', 'name' => 'Shop'],
        ]);
        $this->app->register(MailServiceProvider::class);
        // What the docs ask apps to enable in their Application aliases
        $this->app->alias('mail.manager', MailFactory::class);
    }

    public function test_a_mailable_is_built_and_sent_with_the_global_from_address(): void
    {
        $this->app->make('mailer')->to('ana@example.com')->send(new OrderShippedMail(42));

        $email = $this->sentEmails()[0];

        $this->assertSame('Order #42 shipped', $email->getSubject());
        $this->assertSame('shop@example.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('Shop', $email->getFrom()[0]->getName());
        $this->assertSame('ana@example.com', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('<b>#42</b>', $email->getHtmlBody());
        $this->assertSame('order.txt', $email->getAttachments()[0]->getFilename());
        $this->assertSame('id=42', $email->getAttachments()[0]->getBody());
    }

    public function test_cc_bcc_and_raw_messages(): void
    {
        $this->app->make('mailer')->to('a@example.com')->cc('b@example.com')->bcc('c@example.com')
            ->send(new OrderShippedMail(1));
        $this->app->make('mailer')->raw('Plain text body', function ($message): void {
            $message->to('d@example.com')->subject('Raw');
        });

        [$first, $second] = $this->sentEmails();

        $this->assertSame('b@example.com', $first->getCc()[0]->getAddress());
        $this->assertSame('c@example.com', $first->getBcc()[0]->getAddress());
        $this->assertSame('Raw', $second->getSubject());
        $this->assertSame('Plain text body', $second->getTextBody());
    }

    public function test_a_should_queue_mailable_is_queued_and_sent_by_the_worker(): void
    {
        $this->app->make('mailer')->to('ana@example.com')->send(new QueuedOrderShippedMail(7));

        $this->assertSame([], $this->sentEmails());
        $this->assertSame(1, $this->db()->table('jobs')->count());

        $this->artisan('queue:work', ['connection' => 'database', '--sleep' => 0]);

        $this->assertSame('Order #7 shipped', $this->sentEmails()[0]->getSubject());
        $this->assertSame(0, $this->db()->table('jobs')->count());
    }

    /**
     * @return Email[]
     */
    private function sentEmails(): array
    {
        /** @var ArrayTransport $transport */
        $transport = $this->app->make('mail.manager')->mailer()->getBaseTransport();

        return $transport->messages()->map(fn($sent) => $sent->getOriginalMessage())->values()->all();
    }
}
