<?php

namespace App\Modules\Warehouse\Data;

use Generator;
use InvalidArgumentException;

/**
 * Plantilla de generación masiva de ubicaciones, como la estructura de
 * coordenadas de LS10 en SAP WM: pasillos por letra, estanterías, niveles y,
 * opcionalmente, posiciones por número.
 *
 * El código de cada ubicación concatena las coordenadas con guiones y los
 * números con dos dígitos (A-01-01, o A-01-01-01 con posición), para que el
 * orden alfabético de los códigos coincida con el recorrido físico.
 */
final readonly class LocationGrid
{
    public const MAX_NUMBER = 99;

    private const NUMBER_DIGITS = 2;

    /**
     * @param  array{string, string}  $aisles  Desde y hasta, letras de la A a la Z.
     * @param  array{int, int}  $racks
     * @param  array{int, int}  $levels
     * @param  array{int, int}|null  $positions
     */
    public function __construct(
        public array $aisles,
        public array $racks,
        public array $levels,
        public ?array $positions = null,
    ) {
        self::assertLetterRange($aisles);
        self::assertNumberRange($racks);
        self::assertNumberRange($levels);

        if ($positions !== null) {
            self::assertNumberRange($positions);
        }
    }

    public function count(): int
    {
        return self::span($this->aisles[0], $this->aisles[1])
            * ($this->racks[1] - $this->racks[0] + 1)
            * ($this->levels[1] - $this->levels[0] + 1)
            * ($this->positions === null ? 1 : $this->positions[1] - $this->positions[0] + 1);
    }

    /**
     * Ubicaciones de la plantilla en orden de recorrido.
     *
     * @return Generator<int, array{code: string, aisle: string, rack: string, level: string, position: string|null}>
     */
    public function cells(): Generator
    {
        $positions = $this->positions === null ? [null] : range($this->positions[0], $this->positions[1]);

        foreach (range($this->aisles[0], $this->aisles[1]) as $aisle) {
            foreach (range($this->racks[0], $this->racks[1]) as $rack) {
                foreach (range($this->levels[0], $this->levels[1]) as $level) {
                    foreach ($positions as $position) {
                        $coordinates = [
                            'aisle' => (string) $aisle,
                            'rack' => self::pad($rack),
                            'level' => self::pad($level),
                            'position' => $position === null ? null : self::pad($position),
                        ];

                        yield ['code' => implode('-', array_filter($coordinates, fn (?string $part): bool => $part !== null)), ...$coordinates];
                    }
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        $codes = [];

        foreach ($this->cells() as $cell) {
            $codes[] = $cell['code'];
        }

        return $codes;
    }

    private static function pad(int $number): string
    {
        return str_pad((string) $number, self::NUMBER_DIGITS, '0', STR_PAD_LEFT);
    }

    private static function span(string $from, string $to): int
    {
        return ord($to) - ord($from) + 1;
    }

    /**
     * @param  array{string, string}  $range
     */
    private static function assertLetterRange(array $range): void
    {
        foreach ($range as $letter) {
            if (preg_match('/^[A-Z]$/', $letter) !== 1) {
                throw new InvalidArgumentException("El pasillo {$letter} no es una letra de la A a la Z.");
            }
        }

        if ($range[0] > $range[1]) {
            throw new InvalidArgumentException("El rango de pasillos {$range[0]} a {$range[1]} está invertido.");
        }
    }

    /**
     * @param  array{int, int}  $range
     */
    private static function assertNumberRange(array $range): void
    {
        if ($range[0] < 1 || $range[1] > self::MAX_NUMBER || $range[0] > $range[1]) {
            throw new InvalidArgumentException("El rango {$range[0]} a {$range[1]} no está entre 1 y ".self::MAX_NUMBER.' o está invertido.');
        }
    }
}
