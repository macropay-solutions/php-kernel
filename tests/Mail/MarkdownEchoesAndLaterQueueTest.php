<?php

namespace MacropaySolutions\Kernel\Tests\Mail;

use MacropaySolutions\Kernel\Bus\Queueable;
use MacropaySolutions\Kernel\Contracts\Queue\Factory;
use MacropaySolutions\Kernel\Contracts\Queue\Queue;
use MacropaySolutions\Kernel\Mail\Mailable;
use MacropaySolutions\Kernel\Mail\Mailer;
use MacropaySolutions\Kernel\Mail\Markdown;
use PHPUnit\Framework\TestCase;

class MarkdownEchoesAndLaterQueueTest extends TestCase
{
    public function testEchoedDataCannotInjectMarkdownLinksOrImages(): void
    {
        $html = $this->render('[Verify](https://evil.example/login) ![x](https://evil.example/t.png)');

        $this->assertFalse(\str_contains($html, 'href="https://evil.example'));
        $this->assertFalse(\str_contains($html, 'src="https://evil.example'));
        $this->assertTrue(\str_contains($html, '[Verify](https://evil.example/login)'));
        $this->assertTrue(\str_contains($html, '<a href="https://app.example/x?a[]=1">Open</a>'));
        $this->assertSame('[x](y)', \e('[x](y)'));
    }

    public function testLaterSetsTheQueueNameOnTheMailable(): void
    {
        $queue = new MarkdownEchoesAndLaterQueueTestFactory();
        $mailer = (new \ReflectionClass(Mailer::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($mailer, 'name'))->setValue($mailer, 'smtp');
        $mailer->setQueue($queue);

        $mailer->later(60, new MarkdownEchoesAndLaterQueueTestMailable(), 'emails');

        $this->assertSame(['emails'], $queue->queues);
    }

    public function testPriorityDoesNotKeepTheMailableInACycle(): void
    {
        $mailable = (new MarkdownEchoesAndLaterQueueTestMailable())->priority(1);
        $reference = \WeakReference::create($mailable);
        unset($mailable);

        $this->assertNull($reference->get());
    }

    private function render(string $name): string
    {
        $markdown = (new \ReflectionClass(Markdown::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($markdown, 'view'))->setValue($markdown, new MarkdownEchoesAndLaterQueueTestView());

        return (string)$markdown->render('mail', ['name' => $name], new MarkdownEchoesAndLaterQueueTestInliner());
    }
}

class MarkdownEchoesAndLaterQueueTestView
{
    public function make(string $view, array $data): object
    {
        return new class ($view, $data) {
            public function __construct(private string $view, private array $data)
            {
            }

            public function render(): string
            {
                if ('mail' !== $this->view) {
                    return '';
                }

                // {{ $name }} in the slot the layout parses, {{ $url }} in an attribute outside it
                return Markdown::parse('Hello ' . \e($this->data['name']))
                    . '<a href="' . \e('https://app.example/x?a[]=1') . '">Open</a>';
            }
        };
    }

    public function exists(string $view): bool
    {
        return false;
    }
}

class MarkdownEchoesAndLaterQueueTestInliner
{
    public function convert(string $html, string $css): string
    {
        return $html;
    }
}

class MarkdownEchoesAndLaterQueueTestMailable extends Mailable
{
    use Queueable;

    protected function newQueuedJob()
    {
        return new \stdClass();
    }
}

class MarkdownEchoesAndLaterQueueTestFactory implements Factory
{
    public array $queues = [];

    public function connection($name = null)
    {
        return new class ($this) {
            public function __construct(private MarkdownEchoesAndLaterQueueTestFactory $factory)
            {
            }

            public function laterOn($queue, $delay, $job)
            {
                $this->factory->queues[] = $queue;

                return 1;
            }
        };
    }

    public function fwd(): Queue
    {
        throw new \LogicException('not used');
    }
}
