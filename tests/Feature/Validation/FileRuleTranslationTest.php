<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Validation;

use MacropaySolutions\Kernel\Contracts\Validation\Factory;
use MacropaySolutions\Kernel\Http\UploadedFile;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;
use MacropaySolutions\Kernel\Validation\Rules\File;

/**
 * The File rule translated the messages of its inner validator a second time, using the translated
 * sentences as translation keys.
 */
class FileRuleTranslationTest extends ApplicationTestCase
{
    public function test_messages_are_not_translated_twice(): void
    {
        $missing = [];
        $this->app->make('translator')->handleMissingKeysUsing(function (string $key) use (&$missing): string {
            $missing[] = $key;

            return $key;
        });

        $path = \tempnam(\sys_get_temp_dir(), 'file-rule');
        \file_put_contents($path, '%PDF-1.4');

        try {
            $validator = $this->app->make(Factory::class)->make(
                ['file' => new UploadedFile($path, 'file.pdf', 'application/pdf', null, true)],
                ['file' => [File::types(['txt'])]]
            );

            $this->assertTrue($validator->fails());
            $this->assertNotContains($validator->errors()->first('file'), $missing);
        } finally {
            \unlink($path);
        }
    }
}
