<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Reads the messages back out of a FIT file, so a test can check what a
 * watch would see. It knows the field types Traceway writes, nothing more.
 */
final class FitMessages
{
    /**
     * @return list<array{global: int, fields: array<int, int|string|null>}>
     */
    public static function read(string $bytes): array
    {
        $headerSize = ord($bytes[0]);
        $header = unpack('VdataSize', substr($bytes, 4, 4));

        throw_unless(is_array($header) && is_int($header['dataSize']), RuntimeException::class, 'The FIT header has no data size.');

        $end = $headerSize + $header['dataSize'];
        $offset = $headerSize;
        /** @var array<int, array{global: int, fields: list<array{0: int, 1: int, 2: int}>}> $definitions */
        $definitions = [];
        $messages = [];

        while ($offset < $end) {
            $record = ord($bytes[$offset]);
            $local = $record & 0x0F;
            $offset++;

            if (($record & 0x40) !== 0) {
                $global = unpack('v', substr($bytes, $offset + 2, 2));
                $count = ord($bytes[$offset + 4]);
                $offset += 5;
                $fields = [];

                for ($field = 0; $field < $count; $field++) {
                    $fields[] = [ord($bytes[$offset]), ord($bytes[$offset + 1]), ord($bytes[$offset + 2])];
                    $offset += 3;
                }

                $definitions[$local] = ['global' => is_array($global) && is_int($global[1]) ? $global[1] : -1, 'fields' => $fields];

                continue;
            }

            $definition = $definitions[$local] ?? throw new RuntimeException('A data message has no definition.');
            $values = [];

            foreach ($definition['fields'] as [$number, $size, $type]) {
                $values[$number] = self::value(substr($bytes, $offset, $size), $type);
                $offset += $size;
            }

            $messages[] = ['global' => $definition['global'], 'fields' => $values];
        }

        return $messages;
    }

    /**
     * @return list<array<int, int|string|null>>
     */
    public static function of(string $bytes, int $global): array
    {
        $found = [];

        foreach (self::read($bytes) as $message) {
            if ($message['global'] === $global) {
                $found[] = $message['fields'];
            }
        }

        return $found;
    }

    private static function value(string $raw, int $type): int|string|null
    {
        $unpacked = match ($type) {
            0, 2 => unpack('C', $raw),
            132 => unpack('v', $raw),
            133 => unpack('l', $raw),
            134 => unpack('V', $raw),
            7 => [1 => rtrim($raw, "\0")],
            default => null,
        };

        if (! is_array($unpacked)) {
            return null;
        }

        $value = $unpacked[1] ?? null;

        return is_int($value) || is_string($value) ? $value : null;
    }
}
