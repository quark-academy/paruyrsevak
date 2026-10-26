<?php

namespace App\Support;

/**
 * The five themes a poem can carry, their Armenian labels and the mapping
 * used to fill them for poems that already exist in the database.
 */
final class PoemThemes
{
    /**
     * Stable keys (used in the query string and in the database) and the
     * labels shown to visitors and editors.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'love' => 'սեր',
        'life' => 'կյանք',
        'homeland' => 'հայրենիք',
        'longing' => 'կարոտ',
        'philosophy' => 'փիլիսոփայություն',
    ];

    /**
     * Themes of the poems known at the time the feature was introduced.
     *
     * @var array<string, list<string>>
     */
    private const KNOWN = [
        'աշուն (դեմո)' => ['love', 'longing'],
        'գարուն (դեմո)' => ['love', 'life'],
        'գիշեր (դեմո)' => ['longing', 'philosophy'],
        'անակնկալ հայտնագործություն' => ['life', 'philosophy'],
        'անջատում' => ['love', 'longing'],
        'ապրել, ապրել' => ['life', 'philosophy'],
        'առաջադրանք համայն աշխարհի հաշվիչ մեքենաներին և ճշգ' => ['philosophy', 'life'],
        'գովերգում եմ' => ['life', 'love'],
        'զղջում եմ' => ['longing', 'philosophy'],
        'իմ քաղցրանուն' => ['love', 'longing'],
        'լեռնային գիշեր' => ['homeland', 'longing'],
        'խոստանում եմ' => ['love', 'life'],
        'խուսափում եմ' => ['philosophy', 'longing'],
        'հպարտանում եմ' => ['homeland', 'life'],
        'մարդ կա, մարդ էլ' => ['philosophy', 'life'],
        'մոր ձեռքերը' => ['love', 'longing'],
        'վարք մեծաց' => ['philosophy', 'life'],
        'նորի՛ց չեն սիրում, սիրում են կրկի՜ն' => ['love', 'longing'],
        'անծանոթուհուն' => ['love', 'longing', 'philosophy'],
        'դու' => ['love', 'life', 'philosophy'],
        'ցավն է հաճախ առաջ մղում' => ['philosophy', 'life'],
        'ատում եմ' => ['philosophy', 'life'],
        'քո անունը' => ['love', 'longing'],
        'հայաստան' => ['homeland', 'longing'],
    ];

    /**
     * Keep only real themes, in the canonical order.
     *
     * @param  array<int, mixed>  $themes
     * @return list<string>
     */
    public static function normalize(array $themes): array
    {
        $themes = array_map(static fn ($theme): string => (string) $theme, $themes);

        return array_values(array_intersect(array_keys(self::LABELS), $themes));
    }

    /**
     * Themes for a title: the known mapping first, then a light keyword guess
     * so a newly added poem is never left without any theme.
     *
     * @return list<string>
     */
    public static function forTitle(string $title): array
    {
        $normalized = mb_strtolower(trim($title));

        if (isset(self::KNOWN[$normalized])) {
            return self::KNOWN[$normalized];
        }

        $themes = [];
        $rules = [
            'homeland' => ['հայաստան', 'հայրենիք', 'երկիր', 'լեռ', 'արարատ', 'զվարթնոց'],
            'longing' => ['կարոտ', 'հիշող', 'բացակայ', 'սպասում', 'անուն'],
            'love' => ['սեր', 'սիր', 'սիրելի', 'աչքեր', 'գեղեցիկ'],
            'life' => ['կյանք', 'ապր', 'մահ', 'ճամփ', 'ճանապարհ', 'մարդ'],
            'philosophy' => ['ժամանակ', 'խոսք', 'լույս', 'խավար', 'ճշմարտ', 'միտք'],
        ];

        foreach ($rules as $theme => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    $themes[] = $theme;
                    break;
                }
            }
        }

        return $themes === [] ? ['life', 'philosophy'] : self::normalize($themes);
    }

    /**
     * Armenian labels for the given keys.
     *
     * @param  array<int, mixed>  $themes
     * @return list<string>
     */
    public static function labelsFor(array $themes): array
    {
        return array_values(array_intersect_key(self::LABELS, array_flip(self::normalize($themes))));
    }
}
