<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Alimentação', 'color' => '#3B82F6', 'icon' => 'ki-filled ki-coffee', 'keywords' => ['ARROZ', 'FEIJAO', 'MACARRAO', 'ACUCAR', 'OLEO', 'SAL', 'FARINHA', 'BISCOITO', 'MASSA', 'MOLHO', 'EXTRATO', 'TEMPERO', 'AZEITE', 'CEREAL', 'AVEIA', 'GRANOLA', 'CONSERVA']],
            ['name' => 'Bebidas', 'color' => '#8B5CF6', 'icon' => 'ki-filled ki-cup', 'keywords' => ['CERVEJA', 'REFRI', 'REFRIGERANTE', 'SUCO', 'AGUA', 'CHOPP', 'VODKA', 'VINHO', 'ENERGETICO', 'COCA', 'GUARANA', 'CHA', 'CAFE']],
            ['name' => 'Hortifruti', 'color' => '#22C55E', 'icon' => 'ki-filled ki-tree', 'keywords' => ['TOMATE', 'BANANA', 'BATATA', 'CEBOLA', 'ALFACE', 'MACA', 'LARANJA', 'LIMAO', 'CENOURA', 'PEPINO', 'PIMENTAO', 'ALHO', 'MANGA', 'MELANCIA', 'UVA', 'MORANGO', 'ABACAXI']],
            ['name' => 'Carnes', 'color' => '#EF4444', 'icon' => 'ki-filled ki-burger', 'keywords' => ['CARNE', 'FRANGO', 'LINGUICA', 'SALSICHA', 'BACON', 'PEITO', 'COXA', 'FILE', 'PICANHA', 'COSTELA', 'HAMBURGUER', 'PEIXE', 'ATUM', 'SARDINHA', 'PRESUNTO', 'MORTADELA']],
            ['name' => 'Laticínios', 'color' => '#F59E0B', 'icon' => 'ki-filled ki-drop', 'keywords' => ['LEITE', 'QUEIJO', 'IOGURTE', 'MANTEIGA', 'REQUEIJAO', 'CREME DE LEITE', 'NATA', 'MUSSARELA', 'PRATO']],
            ['name' => 'Padaria', 'color' => '#D97706', 'icon' => 'ki-filled ki-brioche', 'keywords' => ['PAO', 'BOLO', 'TORTA', 'ROSCA', 'CROISSANT', 'SONHO', 'BISNAGUINHA', 'CUCA']],
            ['name' => 'Higiene', 'color' => '#06B6D4', 'icon' => 'ki-filled ki-drop', 'keywords' => ['SHAMPOO', 'SABONETE', 'PASTA DENTAL', 'ESCOVA', 'DESODORANTE', 'PAPEL HIG', 'CREME DENTAL', 'CONDICIONADOR', 'ABSORVENTE', 'FRALDA', 'FIOGUENTAL']],
            ['name' => 'Limpeza', 'color' => '#14B8A6', 'icon' => 'ki-filled ki-shield-tick', 'keywords' => ['DETERGENTE', 'DESINFETANTE', 'AGUA SANITARIA', 'SABAO', 'AMACIANTE', 'ESPONJA', 'ALVEJANTE', 'LIMPADOR', 'PANO', 'LUSTRA', 'INSETICIDA']],
            ['name' => 'Outros', 'color' => '#94A3B8', 'icon' => 'ki-filled ki-dots-circle', 'keywords' => []],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name'], 'user_id' => null],
                $category
            );
        }
    }
}
