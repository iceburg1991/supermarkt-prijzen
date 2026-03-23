<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NormalizedCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Dairy', 'slug' => 'dairy', 'description' => 'Milk, cheese, yogurt, and other dairy products', 'keywords' => 'melk,milk,yoghurt,yogurt,kwark,vla,pudding'],
            ['name' => 'Bread & Bakery', 'slug' => 'bread-bakery', 'description' => 'Bread, pastries, and baked goods', 'keywords' => 'brood,bread,croissant,pistolet,stokbrood,baguette'],
            ['name' => 'Meat & Poultry', 'slug' => 'meat-poultry', 'description' => 'Fresh and processed meat products', 'keywords' => 'vlees,meat,kip,chicken,rund,beef,varken,pork,gehakt,worst,sausage'],
            ['name' => 'Fish & Seafood', 'slug' => 'fish-seafood', 'description' => 'Fresh and frozen fish and seafood', 'keywords' => 'vis,fish,zalm,salmon,tonijn,tuna,garnaal,shrimp,mosselen'],
            ['name' => 'Vegetables', 'slug' => 'vegetables', 'description' => 'Fresh and frozen vegetables', 'keywords' => 'groente,vegetables,tomaat,tomato,komkommer,cucumber,paprika,sla,lettuce,wortel,carrot'],
            ['name' => 'Fruits', 'slug' => 'fruits', 'description' => 'Fresh and frozen fruits', 'keywords' => 'fruit,appel,apple,banaan,banana,sinaasappel,orange,peer,pear,druiven,grapes'],
            ['name' => 'Beverages', 'slug' => 'beverages', 'description' => 'Drinks including water, juice, soda, and more', 'keywords' => 'drank,drink,water,sap,juice,frisdrank,soda,cola,thee,tea,bier,beer,wijn,wine'],
            ['name' => 'Snacks', 'slug' => 'snacks', 'description' => 'Chips, cookies, candy, and other snacks', 'keywords' => 'chips,koek,cookie,snoep,candy,chocolade,chocolate,reep,bar'],
            ['name' => 'Frozen Foods', 'slug' => 'frozen-foods', 'description' => 'Frozen meals, vegetables, and desserts', 'keywords' => 'diepvries,frozen,ijs,ice cream,pizza,frites,fries'],
            ['name' => 'Pantry Staples', 'slug' => 'pantry-staples', 'description' => 'Rice, pasta, canned goods, and cooking essentials', 'keywords' => 'rijst,rice,pasta,macaroni,blik,canned,conserven,olie,oil,azijn,vinegar'],
            ['name' => 'Breakfast', 'slug' => 'breakfast', 'description' => 'Cereals, oatmeal, and breakfast items', 'keywords' => 'ontbijt,breakfast,cornflakes,muesli,havermout,oatmeal,pap'],
            ['name' => 'Coffee', 'slug' => 'coffee', 'description' => 'Coffee beans, ground coffee, and coffee pods', 'keywords' => 'koffie,coffee,espresso,cappuccino,latte'],
            ['name' => 'Cheese', 'slug' => 'cheese', 'description' => 'All types of cheese', 'keywords' => 'kaas,cheese,gouda,cheddar,brie,camembert'],
            ['name' => 'Eggs', 'slug' => 'eggs', 'description' => 'Fresh eggs', 'keywords' => 'eier,egg,scharreleier'],
            ['name' => 'Personal Care', 'slug' => 'personal-care', 'description' => 'Hygiene and personal care products', 'keywords' => 'shampoo,zeep,soap,tandpasta,toothpaste,deo,deodorant'],
            ['name' => 'Household', 'slug' => 'household', 'description' => 'Cleaning supplies and household items', 'keywords' => 'schoonmaak,cleaning,wasmiddel,detergent,afwasmiddel,dish soap'],
            ['name' => 'Baby & Kids', 'slug' => 'baby-kids', 'description' => 'Baby food, diapers, and children\'s products', 'keywords' => 'baby,luier,diaper,babyvoeding,baby food'],
            ['name' => 'Pet Supplies', 'slug' => 'pet-supplies', 'description' => 'Pet food and accessories', 'keywords' => 'huisdier,pet,hond,dog,kat,cat,voer,food'],
        ];

        foreach ($categories as $category) {
            DB::table('normalized_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
