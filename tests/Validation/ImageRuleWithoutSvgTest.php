<?php

namespace MacropaySolutions\Kernel\Tests\Validation;

use MacropaySolutions\Kernel\Http\UploadedFile;
use MacropaySolutions\Kernel\Translation\ArrayLoader;
use MacropaySolutions\Kernel\Translation\Translator;
use MacropaySolutions\Kernel\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ImageRuleWithoutSvgTest extends TestCase
{
    public function testSvgIsNotAnImageButCanBeListedExplicitly(): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'svg');
        \file_put_contents(
            $path,
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="1" height="1"/></svg>'
        );
        $svg = new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true);

        try {
            $this->assertFalse($this->passes(['logo' => $svg], ['logo' => 'image']));
            $this->assertTrue($this->passes(['logo' => $svg], ['logo' => 'file|mimes:png,svg']));
        } finally {
            \unlink($path);
        }
    }

    public function testImagesStillPass(): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'png');
        \file_put_contents($path, \base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        ));

        try {
            $this->assertTrue($this->passes(
                ['photo' => new UploadedFile($path, 'photo.png', 'image/png', null, true)],
                ['photo' => 'image']
            ));
        } finally {
            \unlink($path);
        }
    }

    private function passes(array $data, array $rules): bool
    {
        return (new Validator(new Translator(new ArrayLoader(), 'en'), $data, $rules))->passes();
    }
}
