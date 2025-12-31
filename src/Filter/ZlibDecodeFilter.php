<?php

namespace Ossrock\FflatePhp\Filter;

use Ossrock\FflatePhp\Stream\ZlibStreamDecoder;

class ZlibDecodeFilter extends \php_user_filter
{
    /** @var ZlibStreamDecoder */
    private $dec;
    private $buffer = '';
    private $bufferSize = 8192;

    public function onCreate(): bool
    {
        $this->bufferSize = (int) ($this->params['buffer_size'] ?? 8192);
        $this->dec = new ZlibStreamDecoder();
        return true;
    }

    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;

            // Buffering
            $this->buffer .= $bucket->data;

            if (strlen($this->buffer) >= $this->bufferSize) {
                $decoded = $this->dec->append($this->buffer, false);
                $this->buffer = '';

                if ($decoded !== '') {
                    $ob = stream_bucket_new($this->stream, $decoded);
                    stream_bucket_append($out, $ob);
                }
            }
        }

        if ($closing) {
            // Flush remaining buffer
            if ($this->buffer !== '') {
                $decoded = $this->dec->append($this->buffer, false);
                $this->buffer = '';

                if ($decoded !== '') {
                    $ob = stream_bucket_new($this->stream, $decoded);
                    stream_bucket_append($out, $ob);
                }
            }

            // Finish stream
            $tail = $this->dec->append('', true);
            if ($tail !== '') {
                $ob = stream_bucket_new($this->stream, $tail);
                stream_bucket_append($out, $ob);
            }
        }

        return PSFS_PASS_ON;
    }
}
