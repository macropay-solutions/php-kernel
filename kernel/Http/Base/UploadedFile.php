<?php

namespace MacropaySolutions\Kernel\Http\Base;

use MacropaySolutions\Kernel\Http\FileHelpers;
use MacropaySolutions\Kernel\Support\Traits\Macroable;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

/**
 * Use this class instead of its parent in your code base!!!
 */
class UploadedFile extends SymfonyUploadedFile
{
    use FileHelpers;
    use Macroable;
}
