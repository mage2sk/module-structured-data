<?php
declare(strict_types=1);

namespace Panth\StructuredData\Model\StructuredData;

class MicrodataRemover
{
    private const RAW_BLOCKS = '#(<script\b.*?</script\s*>|<style\b.*?</style\s*>|<textarea\b.*?</textarea\s*>|<!--.*?-->)#is';

    private const TAG = '#<[a-zA-Z](?:"[^"]*"|\'[^\']*\'|[^\'">])*>#';

    private const SCOPE_ATTR = '#\s+itemscope(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>/]+))?(?=[\s/>])#';

    private const VALUE_ATTR = '#\s+(?:itemprop|itemtype|itemid|itemref)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+)#';

    public function remove(string $html): string
    {
        if ($html === '' || strpos($html, 'item') === false) {
            return $html;
        }

        $parts = preg_split(self::RAW_BLOCKS, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1 || strpos($part, 'item') === false) {
                continue;
            }
            $cleaned = preg_replace_callback(
                self::TAG,
                static function (array $match): string {
                    $tag = $match[0];
                    if (strpos($tag, 'item') === false) {
                        return $tag;
                    }
                    $tag = (string) preg_replace(self::SCOPE_ATTR, '', $tag);

                    return (string) preg_replace(self::VALUE_ATTR, '', $tag);
                },
                $part
            );
            $parts[$index] = $cleaned ?? $part;
        }

        return implode('', $parts);
    }
}
