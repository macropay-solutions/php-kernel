<?php

namespace MacropaySolutions\Prompts\Concerns;

use InvalidArgumentException;
use MacropaySolutions\Prompts\ConfirmPrompt;
use MacropaySolutions\Prompts\MultiSearchPrompt;
use MacropaySolutions\Prompts\MultiSelectPrompt;
use MacropaySolutions\Prompts\Note;
use MacropaySolutions\Prompts\PasswordPrompt;
use MacropaySolutions\Prompts\PausePrompt;
use MacropaySolutions\Prompts\Progress;
use MacropaySolutions\Prompts\SearchPrompt;
use MacropaySolutions\Prompts\SelectPrompt;
use MacropaySolutions\Prompts\Spinner;
use MacropaySolutions\Prompts\SuggestPrompt;
use MacropaySolutions\Prompts\Table;
use MacropaySolutions\Prompts\TextareaPrompt;
use MacropaySolutions\Prompts\TextPrompt;
use MacropaySolutions\Prompts\Themes\Default\ConfirmPromptRenderer;
use MacropaySolutions\Prompts\Themes\Default\MultiSearchPromptRenderer;
use MacropaySolutions\Prompts\Themes\Default\MultiSelectPromptRenderer;
use MacropaySolutions\Prompts\Themes\Default\NoteRenderer;
use MacropaySolutions\Prompts\Themes\Default\PasswordPromptRenderer;
use MacropaySolutions\Prompts\Themes\Default\PausePromptRenderer;
use MacropaySolutions\Prompts\Themes\Default\ProgressRenderer;
use MacropaySolutions\Prompts\Themes\Default\SearchPromptRenderer;
use MacropaySolutions\Prompts\Themes\Default\SelectPromptRenderer;
use MacropaySolutions\Prompts\Themes\Default\SpinnerRenderer;
use MacropaySolutions\Prompts\Themes\Default\SuggestPromptRenderer;
use MacropaySolutions\Prompts\Themes\Default\TableRenderer;
use MacropaySolutions\Prompts\Themes\Default\TextareaPromptRenderer;
use MacropaySolutions\Prompts\Themes\Default\TextPromptRenderer;

trait Themes
{
    /**
     * The name of the active theme.
     */
    protected static string $theme = 'default';

    /**
     * The available themes.
     *
     * @var array<string, array<class-string<\MacropaySolutions\Prompts\Prompt>, class-string<object&callable>>>
     */
    protected static array $themes = [
        'default' => [
            TextPrompt::class => TextPromptRenderer::class,
            TextareaPrompt::class => TextareaPromptRenderer::class,
            PasswordPrompt::class => PasswordPromptRenderer::class,
            SelectPrompt::class => SelectPromptRenderer::class,
            MultiSelectPrompt::class => MultiSelectPromptRenderer::class,
            ConfirmPrompt::class => ConfirmPromptRenderer::class,
            PausePrompt::class => PausePromptRenderer::class,
            SearchPrompt::class => SearchPromptRenderer::class,
            MultiSearchPrompt::class => MultiSearchPromptRenderer::class,
            SuggestPrompt::class => SuggestPromptRenderer::class,
            Spinner::class => SpinnerRenderer::class,
            Note::class => NoteRenderer::class,
            Table::class => TableRenderer::class,
            Progress::class => ProgressRenderer::class,
        ],
    ];

    /**
     * Get or set the active theme.
     *
     * @throws \InvalidArgumentException
     */
    public static function theme(?string $name = null): string
    {
        if ($name === null) {
            return static::$theme;
        }

        if (!isset(static::$themes[$name])) {
            throw new InvalidArgumentException("Prompt theme [{$name}] not found.");
        }

        return static::$theme = $name;
    }

    /**
     * Add a new theme.
     *
     * @param array<class-string<\MacropaySolutions\Prompts\Prompt>, class-string<object&callable>> $renderers
     */
    public static function addTheme(string $name, array $renderers): void
    {
        if ($name === 'default') {
            throw new InvalidArgumentException('The default theme cannot be overridden.');
        }

        static::$themes[$name] = $renderers;
    }

    /**
     * Get the renderer for the current prompt.
     */
    protected function getRenderer(): callable
    {
        $class = static::class;

        return new (static::$themes[static::$theme][$class] ?? static::$themes['default'][$class])($this);
    }

    /**
     * Render the prompt using the active theme.
     */
    protected function renderTheme(): string
    {
        $renderer = $this->getRenderer();

        return $renderer($this);
    }
}
