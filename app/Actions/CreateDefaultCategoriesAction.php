<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\User;

/**
 * Cria o conjunto padrão de categorias (com keywords para auto-categorização)
 * para um usuário recém-cadastrado. Compartilhada entre cadastro web e API.
 */
class CreateDefaultCategoriesAction
{
    private const CATEGORIES = [
        ['name' => 'Alimentos', 'color' => '#3b82f6', 'icon' => null, 'keywords' => ['ARROZ', 'FEIJAO', 'MAC', 'MACARRAO', 'AMIDO', 'MAIZENA', 'OLEO', 'SAL', 'FARINHA', 'FLOCOS', 'CALDO', 'TEMP', 'KITANO', 'MAION', 'MOLHO', 'MLH', 'NHOQUE', 'ACHOCOLATADO', 'FERMENTO', 'MIST.BOLO', 'CUP NOOD', 'NAGUMO']],
        ['name' => 'Besteira', 'color' => '#ff0000', 'icon' => null, 'keywords' => ['CHOC', 'BIS', 'MILKA', 'TRENTO', 'KITKAT', 'WFR', 'GOMA', 'SORV', 'TALENTO', 'BBOM', 'CHOCOBISC', 'M PIP', 'CRUNCH', 'ANA MARIA']],
        ['name' => 'Bebidas', 'color' => '#ff7300', 'icon' => null, 'keywords' => ['AGUA', 'REFR', 'REFRIG', 'REFRESCO', 'SUC', 'SUCO', 'CHA', 'CAFE', 'NESCAFE', 'KOPIKO', 'ENERG', 'VINHO', 'H2OH']],
        ['name' => 'Limpeza', 'color' => '#11ff00', 'icon' => null, 'keywords' => ['DET', 'VEJA', 'GLADE', 'ODORIZ', 'PANO', 'L MOV', 'ALVEJANTE']],
        ['name' => 'Hortifruti', 'color' => '#22C55E', 'icon' => 'ki-filled ki-tree', 'keywords' => ['ALHO', 'BATATA', 'CEBOLA', 'CENOURA', 'TOMATE', 'LIMAO', 'ALECRIM', 'TOMILHO', 'SALSAO', 'LEGUME', 'FRUTA', 'HORENSO']],
        ['name' => 'Carnes e Ovos', 'color' => '#EF4444', 'icon' => 'ki-filled ki-burger', 'keywords' => ['CARNE', 'FRANGO', 'FILE', 'FILET', 'EMP FGO', 'PE PERU', 'HAMBURG', 'CHIKEN', 'OVO', 'EMPANADA', 'CHICKEN']],
        ['name' => 'Laticínios', 'color' => '#F59E0B', 'icon' => 'ki-filled ki-drop', 'keywords' => ['LEITE', 'LTE', 'CR LTE', 'CR LEITE', 'MANT', 'MUSS', 'QUEIJO', 'QJ', 'REQUEIJAO', 'CREAM CHE']],
        ['name' => 'Padaria', 'color' => '#D97706', 'icon' => 'ki-filled ki-brioche', 'keywords' => ['PAO', 'PANCO', 'P FORMA', 'TORR', 'MASSA LEVE', 'PIZZA']],
        ['name' => 'Higiene', 'color' => '#06B6D4', 'icon' => 'ki-filled ki-drop', 'keywords' => ['CR D COLG', 'SBT', 'DESOD', 'HAST COT']],
        ['name' => 'Casa e Utilidades', 'color' => '#64748B', 'icon' => 'ki-filled ki-home', 'keywords' => ['PILHA', 'AFIADOR', 'BOWL', 'COPO', 'VELA']],
        ['name' => 'Prontos', 'color' => '#A855F7', 'icon' => 'ki-filled ki-cup', 'keywords' => ['SUSHI', 'URAMAKI', 'BENTO', 'SALADA', 'BARCA']],
    ];

    public function execute(User $user): void
    {
        foreach (self::CATEGORIES as $category) {
            Category::firstOrCreate(
                ['user_id' => $user->id, 'name' => $category['name']],
                $category
            );
        }
    }
}
