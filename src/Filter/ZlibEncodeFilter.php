<?php

namespace Ossrock\FflatePhp\Filter;

use Ossrock\FflatePhp\Stream\ZlibStreamEncoder;

class ZlibEncodeFilter extends \php_user_filter
{
    private $enc;
    private $buffer = '';
    private $bufferSize = 8192;

    public function onCreate(): bool
    {
        $level = (int) ($this->params['level'] ?? 6);
        $this->bufferSize = (int) ($this->params['buffer_size'] ?? 8192);
        $this->enc = new ZlibStreamEncoder($level);
        return true;
    }

    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;

            // Buffering
            $this->buffer .= $bucket->data;

            if (strlen($this->buffer) >= $this->bufferSize) {
                $encoded = $this->enc->append($this->buffer);
                $this->buffer = '';

                if ($encoded !== '') {
                    $ob = stream_bucket_new($this->stream, $encoded);
                    stream_bucket_append($out, $ob);
                }
            }
        }

        if ($closing) {
            // Flush remaining buffer
            if ($this->buffer !== '') {
                $encoded = $this->enc->append($this->buffer);
                $this->buffer = '';

                if ($encoded !== '') {
                    $ob = stream_bucket_new($this->stream, $encoded);
                    stream_bucket_append($out, $ob);
                }
            }

            // Finish stream
            $tail = $this->enc->finish();
            if ($tail !== '') {
                $ob = stream_bucket_new($this->stream, $tail);
                stream_bucket_append($out, $ob);
            }
        }

        return PSFS_PASS_ON;
    }
}
