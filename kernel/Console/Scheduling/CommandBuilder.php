<?php

namespace MacropaySolutions\Kernel\Console\Scheduling;

use MacropaySolutions\Kernel\Console\Application;
use MacropaySolutions\Kernel\Support\ProcessUtils;

class CommandBuilder
{
    /**
     * Build the command for the given event.
     *
     * @param \MacropaySolutions\Kernel\Console\Scheduling\Event $event
     * @return string
     */
    public function buildCommand(Event $event)
    {
        if ($event->runInBackground) {
            return $this->buildBackgroundCommand($event);
        }

        return $this->buildForegroundCommand($event);
    }

    /**
     * Build the command for running the event in the foreground.
     *
     * @param \MacropaySolutions\Kernel\Console\Scheduling\Event $event
     * @return string
     */
    protected function buildForegroundCommand(Event $event)
    {
        $output = ProcessUtils::escapeArgument($event->output);

        return $this->ensureCorrectUser(
            $event,
            $event->command . ($event->shouldAppendOutput ? ' >> ' : ' > ') . $output . ' 2>&1'
        );
    }

    /**
     * Build the command for running the event in the background.
     *
     * @param \MacropaySolutions\Kernel\Console\Scheduling\Event $event
     * @return string
     */
    protected function buildBackgroundCommand(Event $event)
    {
        $output = ProcessUtils::escapeArgument($event->output);

        $redirect = $event->shouldAppendOutput ? ' >> ' : ' > ';

        $finished = Application::formatCommandString('schedule:finish') . ' ' . ProcessUtils::escapeArgument(
            $event->mutexName()
        );

        if (windows_os()) {
            return 'start /b cmd /v:on /c "(' . $event->command . ' & ' . $finished . ' ^!ERRORLEVEL^!)' . $redirect .
                $output . ' 2>&1"';
        }

        return $this->ensureCorrectUser(
            $event,
            '(' . $event->command . $redirect . $output . ' 2>&1 ; ' . $finished . ' "$?") > '
            . $output . ' 2>&1 &'
        );
    }

    /**
     * Finalize the event's command syntax with the correct user.
     */
    protected function ensureCorrectUser(Event $event, string $command): string
    {
        if ('' === (string)$event->user || windows_os()) {
            return $command;
        }

        return 'sudo -u ' . ProcessUtils::escapeArgument($event->user) . ' -- sh -c ' . ProcessUtils::escapeArgument(
            $command
        );
    }
}
