<?php
/**
 * Crucintarsio 15x15: stesso algoritmo di cruciverba-inglese.html (Genera crucintarsio).
 *
 * @package LLM_Tabelle
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LLM_Crucintarsio_Generator {

	/**
	 * Normalizza una parola per la griglia: maiuscole A-Z, senza accenti.
	 *
	 * @param string $raw Parola grezza.
	 * @return string
	 */
	public static function normalize_word( $raw ) {
		$raw = strtoupper( trim( (string) $raw ) );
		if ( function_exists( 'remove_accents' ) ) {
			$raw = remove_accents( $raw );
		}
		$raw = preg_replace( '/[^A-Z]/', '', $raw );
		return is_string( $raw ) ? $raw : '';
	}

	/**
	 * Genera più varianti e ne sceglie una.
	 *
	 * @param string[] $words     Parole A-Z.
	 * @param int      $size      Lato griglia (es. 15).
	 * @param int      $variants  Quante varianti distinte tenere.
	 * @param int      $intrecci  Obiettivo: quante parole incastrate. 0 = il massimo possibile.
	 * @param int      $tries     Tentativi casuali per variante.
	 * @return array{csv:string,placed:string[],unplaced:string[],rows:int,cols:int,placed_count:int}|WP_Error
	 */
	public static function build_best( array $words, $size = 15, $variants = 6, $intrecci = 0, $tries = 400 ) {
		$size     = max( 5, min( LLM_Crossword::MAX_SIDE, absint( $size ) ) );
		$variants = max( 1, min( 20, absint( $variants ) ) );
		$intrecci = absint( $intrecci );
		$tries    = max( 20, min( 800, absint( $tries ) ) );

		$clean = array();
		$seen  = array();
		foreach ( $words as $w ) {
			$n = self::normalize_word( $w );
			if ( strlen( $n ) < 2 || strlen( $n ) > $size ) {
				continue;
			}
			if ( isset( $seen[ $n ] ) ) {
				continue;
			}
			$seen[ $n ] = true;
			$clean[]    = $n;
		}

		if ( count( $clean ) < 2 ) {
			return new WP_Error( 'llm_ci_few_words', 'Servono almeno due parole diverse (lettere A-Z, lunghezza 2–' . $size . ').' );
		}

		$found   = array();
		$seen_csv = array();

		for ( $v = 0; $v < $variants; $v++ ) {
			$best = null;
			for ( $i = 0; $i < $tries; $i++ ) {
				$res = self::generate( $clean, $size, $v * 100003 + $i * 7919 + 1 );
				if ( ! $res ) {
					continue;
				}
				if ( ! $best || count( $res['placed'] ) > count( $best['placed'] ) ) {
					$best = $res;
				}
			}
			if ( ! $best ) {
				continue;
			}
			$trimmed = self::trim_grid( $best['grid'] );
			$csv     = self::grid_to_csv( $trimmed );
			if ( isset( $seen_csv[ $csv ] ) ) {
				continue;
			}
			$seen_csv[ $csv ] = true;
			$found[]          = array(
				'csv'          => $csv,
				'placed'       => $best['placed'],
				'unplaced'     => $best['unplaced'],
				'placed_count' => count( $best['placed'] ),
				'rows'         => count( $trimmed ),
				'cols'         => isset( $trimmed[0] ) ? count( $trimmed[0] ) : 0,
			);
		}

		if ( empty( $found ) ) {
			return new WP_Error( 'llm_ci_none', 'Nessun crucintarsio generato: prova più parole o una griglia più grande.' );
		}

		return self::pick_variant( $found, $intrecci );
	}

	/**
	 * @param array<int,array<string,mixed>> $found    Varianti.
	 * @param int                            $intrecci Obiettivo parole incastrate (0 = max).
	 * @return array<string,mixed>
	 */
	private static function pick_variant( array $found, $intrecci ) {
		usort(
			$found,
			static function ( $a, $b ) use ( $intrecci ) {
				if ( $intrecci > 0 ) {
					$da = abs( (int) $a['placed_count'] - $intrecci );
					$db = abs( (int) $b['placed_count'] - $intrecci );
					if ( $da !== $db ) {
						return $da - $db;
					}
				}
				return (int) $b['placed_count'] - (int) $a['placed_count'];
			}
		);
		return $found[0];
	}

	/**
	 * Un tentativo di incastro.
	 *
	 * @param string[] $words Parole già normalizzate.
	 * @param int      $size  Lato.
	 * @param int      $seed  Seme RNG.
	 * @return array{grid:array,placed:string[],unplaced:string[]}|null
	 */
	public static function generate( array $words, $size, $seed ) {
		$rng  = self::make_rng( $seed );
		$grid = array();
		for ( $r = 0; $r < $size; $r++ ) {
			$grid[ $r ] = array_fill( 0, $size, null );
		}

		$sorted = $words;
		usort(
			$sorted,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		$sorted = array_values(
			array_filter(
				$sorted,
				static function ( $w ) use ( $size ) {
					return strlen( $w ) <= $size;
				}
			)
		);
		if ( empty( $sorted ) ) {
			return null;
		}

		$first = $sorted[0];
		$r0    = (int) floor( $size / 2 );
		$c0    = (int) floor( ( $size - strlen( $first ) ) / 2 );
		self::place( $grid, $first, $r0, $c0, true );
		$placed    = array( $first );
		$remaining = self::shuffle_with( array_slice( $sorted, 1 ), $rng );
		$progress  = true;

		while ( $progress ) {
			$progress = false;
			foreach ( array_values( $remaining ) as $word ) {
				$cands = array();
				for ( $r = 0; $r < $size; $r++ ) {
					for ( $c = 0; $c < $size; $c++ ) {
						$ch = $grid[ $r ][ $c ];
						if ( null === $ch ) {
							continue;
						}
						$len = strlen( $word );
						for ( $i = 0; $i < $len; $i++ ) {
							if ( $word[ $i ] !== $ch ) {
								continue;
							}
							$cands[] = array(
								'r'     => $r - $i,
								'c'     => $c,
								'horiz' => false,
							);
							$cands[] = array(
								'r'     => $r,
								'c'     => $c - $i,
								'horiz' => true,
							);
						}
					}
				}
				$cands = self::shuffle_with( $cands, $rng );
				$best  = null;
				foreach ( $cands as $cand ) {
					$crosses = self::can_place( $grid, $size, $word, $cand['r'], $cand['c'], $cand['horiz'] );
					if ( $crosses > 0 && ( ! $best || $crosses > $best['crosses'] ) ) {
						$best = array(
							'r'       => $cand['r'],
							'c'       => $cand['c'],
							'horiz'   => $cand['horiz'],
							'crosses' => $crosses,
						);
					}
				}
				if ( $best ) {
					self::place( $grid, $word, $best['r'], $best['c'], $best['horiz'] );
					$placed[]  = $word;
					$remaining = array_values(
						array_filter(
							$remaining,
							static function ( $w ) use ( $word ) {
								return $w !== $word;
							}
						)
					);
					$progress = true;
				}
			}
		}

		return array(
			'grid'     => $grid,
			'placed'   => $placed,
			'unplaced' => $remaining,
		);
	}

	/**
	 * @param array $grid  Griglia.
	 * @param int   $size  Lato.
	 * @param string $word Parola.
	 * @param int   $r     Riga.
	 * @param int   $c     Colonna.
	 * @param bool  $horiz Orizzontale.
	 * @return int Croci, oppure -1 se non si può.
	 */
	private static function can_place( array $grid, $size, $word, $r, $c, $horiz ) {
		$n = strlen( $word );
		if ( $horiz ) {
			if ( $c < 0 || $c + $n > $size || $r < 0 || $r >= $size ) {
				return -1;
			}
			if ( self::cell( $grid, $size, $r, $c - 1 ) || self::cell( $grid, $size, $r, $c + $n ) ) {
				return -1;
			}
		} else {
			if ( $r < 0 || $r + $n > $size || $c < 0 || $c >= $size ) {
				return -1;
			}
			if ( self::cell( $grid, $size, $r - 1, $c ) || self::cell( $grid, $size, $r + $n, $c ) ) {
				return -1;
			}
		}

		$crosses = 0;
		for ( $i = 0; $i < $n; $i++ ) {
			$rr       = $horiz ? $r : $r + $i;
			$cc       = $horiz ? $c + $i : $c;
			$existing = self::cell( $grid, $size, $rr, $cc );
			if ( $existing ) {
				if ( $existing !== $word[ $i ] ) {
					return -1;
				}
				++$crosses;
			} elseif ( $horiz ) {
				if ( self::cell( $grid, $size, $rr - 1, $cc ) || self::cell( $grid, $size, $rr + 1, $cc ) ) {
					return -1;
				}
			} else {
				if ( self::cell( $grid, $size, $rr, $cc - 1 ) || self::cell( $grid, $size, $rr, $cc + 1 ) ) {
					return -1;
				}
			}
		}

		return $crosses;
	}

	/**
	 * @param array  $grid  Griglia (by ref).
	 * @param string $word  Parola.
	 * @param int    $r     Riga.
	 * @param int    $c     Colonna.
	 * @param bool   $horiz Orizzontale.
	 */
	private static function place( array &$grid, $word, $r, $c, $horiz ) {
		$n = strlen( $word );
		for ( $i = 0; $i < $n; $i++ ) {
			$rr                 = $horiz ? $r : $r + $i;
			$cc                 = $horiz ? $c + $i : $c;
			$grid[ $rr ][ $cc ] = $word[ $i ];
		}
	}

	/**
	 * @param array $grid Griglia.
	 * @param int   $size Lato.
	 * @param int   $r    Riga.
	 * @param int   $c    Colonna.
	 * @return string|null
	 */
	private static function cell( array $grid, $size, $r, $c ) {
		if ( $r < 0 || $r >= $size || $c < 0 || $c >= $size ) {
			return null;
		}
		return $grid[ $r ][ $c ];
	}

	/**
	 * @param array $grid Griglia con null = vuoto.
	 * @return array Griglia ritagliata.
	 */
	private static function trim_grid( array $grid ) {
		$min_r = PHP_INT_MAX;
		$max_r = -1;
		$min_c = PHP_INT_MAX;
		$max_c = -1;
		$rows  = count( $grid );
		$cols  = $rows ? count( $grid[0] ) : 0;
		for ( $r = 0; $r < $rows; $r++ ) {
			for ( $c = 0; $c < $cols; $c++ ) {
				if ( null !== $grid[ $r ][ $c ] ) {
					if ( $r < $min_r ) {
						$min_r = $r;
					}
					if ( $r > $max_r ) {
						$max_r = $r;
					}
					if ( $c < $min_c ) {
						$min_c = $c;
					}
					if ( $c > $max_c ) {
						$max_c = $c;
					}
				}
			}
		}
		if ( $max_r < 0 ) {
			return $grid;
		}
		$out = array();
		for ( $r = $min_r; $r <= $max_r; $r++ ) {
			$out[] = array_slice( $grid[ $r ], $min_c, $max_c - $min_c + 1 );
		}
		return $out;
	}

	/**
	 * @param array $grid Griglia ritagliata.
	 * @return string CSV.
	 */
	private static function grid_to_csv( array $grid ) {
		$lines = array();
		foreach ( $grid as $row ) {
			$cells = array();
			foreach ( $row as $ch ) {
				$cells[] = null === $ch ? '#' : $ch;
			}
			$lines[] = implode( ',', $cells );
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param int $seed Seme.
	 * @return callable
	 */
	private static function make_rng( $seed ) {
		$s = (int) $seed & 0xFFFFFFFF;
		return static function () use ( &$s ) {
			$s = ( ( $s * 1664525 ) + 1013904223 ) & 0xFFFFFFFF;
			return $s / 4294967296;
		};
	}

	/**
	 * @param array    $arr Lista.
	 * @param callable $rng RNG.
	 * @return array
	 */
	private static function shuffle_with( array $arr, $rng ) {
		$a = array_values( $arr );
		for ( $i = count( $a ) - 1; $i > 0; $i-- ) {
			$j        = (int) floor( $rng() * ( $i + 1 ) );
			$tmp      = $a[ $i ];
			$a[ $i ]  = $a[ $j ];
			$a[ $j ]  = $tmp;
		}
		return $a;
	}
}
