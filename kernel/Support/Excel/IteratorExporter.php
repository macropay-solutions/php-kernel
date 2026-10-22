<?php

namespace MacropaySolutions\Kernel\Support\Excel;

use MacropaySolutions\Kernel\Contracts\Support\Arrayable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use RuntimeException;

class IteratorExporter
{
    /**
     * Streams an iterable collection of arrays/objects as an Excel download.
     * @throws \Throwable
     */
    public function streamDownload(
        iterable $iterable,
        string $filename = 'export.xlsx',
        array $responseHeaders = []
    ): StreamedResponse
    {
        return \app(StreamedResponse::class, [function () use ($iterable) {
            $writer = $this->getWriter();
            $opened = false;

            try {
                $writer->openToFile('php://output');
                $opened = true;

                $this->addIterableToWriter($iterable, $writer);
            } finally {
                if ($opened) {
                    $writer->close();
                }
            }
        }, 200, \array_merge([
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' =>
                HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, 'export.xlsx'),
            'Cache-Control' => 'max-age=0',
        ], $responseHeaders)]);
    }

    /**
     * Stores an iterable collection to a local Excel file.
     *
     * To upload to a remote disk (like AWS S3), store the file locally in a
     * temporary path first, then write the contents of the temporary file to S3
     * using the filesystem driver.
     *
     * @example
     * $tmpFile = \tempnam(\sys_get_temp_dir(), 'excel_');
     *
     * try {
     *     \app(IteratorExporter::class)->store($collection, $tmpFile);
     *     \app('filesystem')->disk('s3')->put('exports/file.xlsx', \fopen($tmpFile, 'r'));
     * } finally {
     *     \unlink($tmpFile);
     * }
     *
     * @param iterable $iterable
     * @param string $filePath
     * @throws \Throwable
     */
    public function store(iterable $iterable, string $filePath): void
    {
        $writer = $this->getWriter();
        $opened = false;

        try {
            $writer->openToFile($filePath);
            $opened = true;

            $this->addIterableToWriter($iterable, $writer);
        } finally {
            if ($opened) {
                $writer->close();
            }
        }
    }

    protected function getWriter(): Writer
    {
        if (!\class_exists(Writer::class)) {
            throw new RuntimeException(
                'openspout/openspout is required to export collections to Excel. Please install it.'
            );
        }

        return new Writer();
    }

    protected function addIterableToWriter(iterable $iterable, Writer $writer): void
    {
        foreach ($iterable as $item) {
            $values = $this->extractRowValues($item);
            $safeValues = [];

            foreach ($values as $value) {
                if (\is_array($value) || \is_object($value)) {
                    try {
                        $safeValues[] = \json_encode($value, \JSON_THROW_ON_ERROR);
                    } catch (\JsonException $e) {
                        $safeValues[] = '[UNENCODABLE_VALUE]';
                    }

                    continue;
                }

                if (\is_resource($value)) {
                    $safeValues[] = '[RESOURCE]';

                    continue;
                }

                if (\is_string($value) && !\mb_check_encoding($value, 'UTF-8')) {
                    $safeValues[] = '[INVALID_UTF8]';

                    continue;
                }

                $safeValues[] = $value;
            }

            $writer->addRow(Row::fromValues($safeValues));
        }
    }

    protected function extractRowValues(mixed $item): array
    {
        if (\is_array($item)) {
            return $item;
        }

        if (!\is_object($item)) {
            return (array)$item;
        }

        if ($item instanceof Arrayable) {
            $array = $item->toArray();

            if (\is_array($array)) {
                return $array;
            }
        }

        if ($item instanceof \JsonSerializable) {
            $serialized = $item->jsonSerialize();

            if (\is_array($serialized)) {
                return $serialized;
            }

            if (\is_object($serialized)) {
                return \get_object_vars($serialized);
            }

            return [$serialized];
        }

        return \get_object_vars($item);
    }
}
