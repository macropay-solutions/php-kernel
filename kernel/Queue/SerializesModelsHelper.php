<?php

namespace MacropaySolutions\Kernel\Queue;

class SerializesModelsHelper
{
    use SerializesAndRestoresModelIdentifiers;

    public function restorePropertyValue(mixed $value): mixed
    {
        return $this->getRestoredPropertyValue($value);
    }

    public function serializePropertyValue(mixed $value): mixed
    {
        return $this->getSerializedPropertyValue($value);
    }
}
