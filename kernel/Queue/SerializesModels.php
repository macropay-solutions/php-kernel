<?php

namespace MacropaySolutions\Kernel\Queue;

trait SerializesModels
{
    use SerializesAndRestoresModelIdentifiers;

    /**
     * Prepare the instance for native PHP serialization.
     */
    public function __serialize(): array
    {
        $values = [];

        foreach (\get_object_vars($this) as $key => $value) {
            $values[$key] = $this->getSerializedPropertyValue($value);
        }

        return $values;
    }

    /**
     * Restore the model properties after serialization or JSON decoding.
     */
    public function __unserialize(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!\property_exists($this, $key)) {
                continue;
            }

            $this->{$key} = $this->getRestoredPropertyValue($value);
        }
    }
}
