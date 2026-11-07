<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Prompts;

use MacropaySolutions\Prompts\Key;
use MacropaySolutions\Prompts\Prompt;
use PHPUnit\Framework\TestCase;

use function MacropaySolutions\Prompts\confirm;
use function MacropaySolutions\Prompts\select;
use function MacropaySolutions\Prompts\text;

/**
 * Console prompts driven by faked key presses: input, validation, required answers, selection and confirmation.
 */
class PromptsTest extends TestCase
{
    protected function tearDown(): void
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function test_text_returns_the_typed_answer(): void
    {
        Prompt::fake(['A', 'n', 'a', Key::ENTER]);

        $this->assertSame('Ana', text('Name?'));
        Prompt::assertOutputContains('Name?');
    }

    public function test_text_validation_shows_the_error_and_asks_again(): void
    {
        Prompt::fake(['x', Key::ENTER, Key::BACKSPACE, '4', '2', Key::ENTER]);

        $answer = text(
            'Age?',
            validate: static fn(string $value): ?string => \ctype_digit($value) ? null : 'Digits only.'
        );

        $this->assertSame('42', $answer);
        Prompt::assertOutputContains('Digits only.');
    }

    public function test_a_required_text_refuses_an_empty_answer(): void
    {
        Prompt::fake([Key::ENTER, 'o', 'k', Key::ENTER]);

        $this->assertSame('ok', text('Reason?', required: 'A reason is required.'));
        Prompt::assertOutputContains('A reason is required.');
    }

    public function test_select_and_confirm(): void
    {
        Prompt::fake([Key::DOWN, Key::ENTER, 'n', Key::ENTER]);

        $this->assertSame('redis', select('Driver?', ['database' => 'Database', 'redis' => 'Redis']));
        $this->assertFalse(confirm('Continue?'));
    }

    public function test_non_interactive_prompts_return_the_default(): void
    {
        Prompt::interactive(false);

        try {
            $this->assertSame('fallback', text('Name?', default: 'fallback'));
        } finally {
            Prompt::interactive(true);
        }
    }
}
