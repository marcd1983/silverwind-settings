<?php

namespace Antlion\SilverwindSettings\Tokens;

use InvalidArgumentException;

/**
 * A design token file, flattened to "path => token" with references
 * resolved. Accepts the common exports:
 *
 *   W3C design tokens (DTCG; Figma's variable export, most plugins)
 *     {"color": {"primary": {"$value": "#2f6b3a", "$type": "color"}}}
 *   Tokens Studio
 *     {"global": {"color": {"primary": {"value": "#2f6b3a", "type": "color"}}}}
 *   Plain maps
 *     {"color/primary": "#2f6b3a", "font/heading": "Lora"}
 *
 * Paths are lower-cased with "/" separators ("Color.Primary" and
 * "color / primary" both become "color/primary"). References like
 * "{color.brand.500}" are resolved, also inside other values.
 */
class TokenFile
{
    /** @var array<string, array{value: mixed, type: ?string}> */
    private array $tokens = [];

    public static function fromFile(string $path): static
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Token file not found: {$path}");
        }

        return static::fromJSON((string) file_get_contents($path));
    }

    public static function fromJSON(string $json): static
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException('The token file is not valid JSON: ' . json_last_error_msg());
        }

        return static::fromArray($data);
    }

    public static function fromArray(array $data): static
    {
        $file = new static();
        $file->collect($data, [], null);
        $file->resolveReferences();

        return $file;
    }

    /** @return array<string, array{value: mixed, type: ?string}> */
    public function all(): array
    {
        return $this->tokens;
    }

    public static function normalisePath(string $path): string
    {
        $segments = preg_split('#\s*[./]\s*#', strtolower(trim($path))) ?: [];
        $segments = array_map(fn ($s) => preg_replace('/\s+/', '-', trim($s)), $segments);

        return implode('/', array_filter($segments, fn ($s) => $s !== ''));
    }

    private function collect(array $node, array $path, ?string $inheritedType): void
    {
        // A group can set $type for all its tokens (DTCG)
        $groupType = $node['$type'] ?? $inheritedType;

        foreach ($node as $key => $child) {
            $key = (string) $key;
            if ($key === '' || $key[0] === '$') {
                continue; // $type, $description, $extensions...
            }
            $childPath = [...$path, $key];

            if (!is_array($child)) {
                // Plain map: "color/primary": "#hex"
                $this->add($childPath, $child, $groupType);
                continue;
            }
            if (array_key_exists('$value', $child)) {
                $this->add($childPath, $child['$value'], $child['$type'] ?? $groupType);
                continue;
            }
            if (array_key_exists('value', $child) && !is_array($child['value']) || $this->isTokensStudioToken($child)) {
                $this->add($childPath, $child['value'], $child['type'] ?? $groupType);
                continue;
            }
            $this->collect($child, $childPath, $groupType);
        }
    }

    /** Tokens Studio tokens have "value" plus "type", even when the value is an object (e.g. typography) */
    private function isTokensStudioToken(array $node): bool
    {
        return array_key_exists('value', $node) && array_key_exists('type', $node);
    }

    private function add(array $path, mixed $value, ?string $type): void
    {
        $key = static::normalisePath(implode('/', $path));
        if ($key !== '') {
            $this->tokens[$key] = ['value' => $value, 'type' => $type];
        }
    }

    private function resolveReferences(): void
    {
        foreach (array_keys($this->tokens) as $path) {
            $value = $this->tokens[$path]['value'];
            // A token that is only a reference takes the referenced token's type (DTCG)
            if ($this->tokens[$path]['type'] === null && is_string($value)
                && preg_match('/^\{([^{}]+)\}$/', trim($value), $m)) {
                $this->tokens[$path]['type'] = $this->typeOf($m[1], [$path]);
            }
            $this->tokens[$path]['value'] = $this->resolve($value, [$path]);
        }
    }

    private function resolve(mixed $value, array $seen): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->resolve($v, $seen), $value);
        }
        if (!is_string($value) || !str_contains($value, '{')) {
            return $value;
        }

        // A whole-value reference keeps the referenced token's type (e.g. a colour object)
        if (preg_match('/^\{([^{}]+)\}$/', trim($value), $m)) {
            return $this->lookup($m[1], $seen) ?? $value;
        }

        // References inside a larger string, e.g. "calc({size.base} * 2)"
        return preg_replace_callback('/\{([^{}]+)\}/', function (array $m) use ($seen) {
            $resolved = $this->lookup($m[1], $seen);

            return is_scalar($resolved) ? (string) $resolved : $m[0];
        }, $value);
    }

    /** Type of the token a reference points to, following chains of references */
    private function typeOf(string $reference, array $seen): ?string
    {
        $target = $this->targetPath($reference);
        if ($target === null || in_array($target, $seen, true)) {
            return null;
        }
        $token = $this->tokens[$target];
        if ($token['type'] !== null) {
            return $token['type'];
        }
        if (is_string($token['value']) && preg_match('/^\{([^{}]+)\}$/', trim($token['value']), $m)) {
            return $this->typeOf($m[1], [...$seen, $target]);
        }

        return null;
    }

    /** The token path a reference points to, allowing the collection to be omitted */
    private function targetPath(string $reference): ?string
    {
        $target = static::normalisePath($reference);
        if (isset($this->tokens[$target])) {
            return $target;
        }
        foreach (array_keys($this->tokens) as $path) {
            if (str_ends_with($path, '/' . $target)) {
                return $path;
            }
        }

        return null;
    }

    private function lookup(string $reference, array $seen): mixed
    {
        $target = static::normalisePath($reference);
        if (in_array($target, $seen, true)) {
            return null; // circular
        }
        $match = $this->tokens[$target] ?? null;
        if ($match === null) {
            // Exported files often drop the top-level collection from references
            foreach ($this->tokens as $path => $token) {
                if (str_ends_with($path, '/' . $target)) {
                    $match = $token;
                    $target = $path;
                    break;
                }
            }
        }

        return $match === null ? null : $this->resolve($match['value'], [...$seen, $target]);
    }
}
