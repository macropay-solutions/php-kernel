<?php

namespace MacropaySolutions\Framework\Bus;

class PendingCallableDispatch extends PendingDispatch
{
    /**
     * Add a callback to be executed if the job fails.
     */
    public function catch(array $callback): static
    {
        $this->job->onFailure($callback);

        return $this;
    }
}
