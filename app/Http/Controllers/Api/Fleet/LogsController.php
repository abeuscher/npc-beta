<?php

namespace App\Http\Controllers\Api\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LogsController extends Controller
{
    private const DEFAULT_LINES = 500;
    private const MAX_LINES = 10000;
    private const MAX_BYTES = 870400;
    private const SOURCE = 'laravel.log';
    private const CHUNK_SIZE = 8192;

    public function index(Request $request): JsonResponse
    {
        $linesParam = $request->query('lines', (string) self::DEFAULT_LINES);

        if (! is_string($linesParam) && ! is_numeric($linesParam)) {
            return $this->invalidLines();
        }

        if (! preg_match('/^\d+$/', (string) $linesParam) || (int) $linesParam < 1) {
            return $this->invalidLines();
        }

        $requestedLines = min((int) $linesParam, self::MAX_LINES);

        $disk = Storage::disk('logs');

        if (! $disk->exists(self::SOURCE)) {
            return response()->json([
                'error'   => 'log_not_found',
                'message' => 'log file does not exist',
            ], 404);
        }

        try {
            $result = $this->tailFile($disk->path(self::SOURCE), $requestedLines, self::MAX_BYTES);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'log_unreadable',
                'message' => class_basename($e),
            ], 500);
        }

        return response()->json([
            'lines'           => $result['lines'],
            'lines_returned'  => count($result['lines']),
            'lines_truncated' => $result['truncated'],
            'source'          => self::SOURCE,
        ]);
    }

    private function invalidLines(): JsonResponse
    {
        return response()->json([
            'error'   => 'invalid_lines',
            'message' => 'lines must be a positive integer between 1 and ' . self::MAX_LINES,
        ], 422);
    }

    private function tailFile(string $path, int $maxLines, int $maxBytes): array
    {
        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            throw new RuntimeException('fopen failed');
        }

        try {
            if (fseek($fp, 0, SEEK_END) !== 0) {
                throw new RuntimeException('fseek end failed');
            }
            $position = ftell($fp);
            if ($position === false) {
                throw new RuntimeException('ftell failed');
            }

            if ($position > 0) {
                if (fseek($fp, $position - 1) !== 0) {
                    throw new RuntimeException('fseek tail-probe failed');
                }
                if (fread($fp, 1) === "\n") {
                    $position--;
                }
            }

            $remainder = '';
            $lines = [];
            $bytes = 0;
            $truncated = false;

            while ($position > 0) {
                $readSize = (int) min(self::CHUNK_SIZE, $position);
                $position -= $readSize;
                if (fseek($fp, $position) !== 0) {
                    throw new RuntimeException('fseek backward failed');
                }
                $chunk = fread($fp, $readSize);
                if ($chunk === false) {
                    throw new RuntimeException('fread failed');
                }
                $buffer = $chunk . $remainder;

                if ($position === 0) {
                    $segments = explode("\n", $buffer);
                    $remainder = '';
                } else {
                    $firstNewline = strpos($buffer, "\n");
                    if ($firstNewline === false) {
                        $remainder = $buffer;
                        continue;
                    }
                    $remainder = substr($buffer, 0, $firstNewline);
                    $rest = substr($buffer, $firstNewline + 1);
                    $segments = explode("\n", $rest);
                }

                for ($i = count($segments) - 1; $i >= 0; $i--) {
                    $line = $segments[$i];
                    $lineSize = strlen($line) + 1;
                    if ($bytes + $lineSize > $maxBytes) {
                        $truncated = true;
                        break 2;
                    }
                    $lines[] = $line;
                    $bytes += $lineSize;
                    if (count($lines) >= $maxLines) {
                        if ($position > 0 || $remainder !== '' || $i > 0) {
                            $truncated = true;
                        }
                        break 2;
                    }
                }
            }

            return [
                'lines'     => array_reverse($lines),
                'truncated' => $truncated,
            ];
        } finally {
            fclose($fp);
        }
    }
}
