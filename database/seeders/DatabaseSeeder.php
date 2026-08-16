<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Variant;
use App\Models\VariantAttribute;
use App\Models\VariantAttributeOption;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name_ar' => 'المقاعد', 'slug' => 'seating', 'description_ar' => 'قطع جلوس هادئة للمساحات اليومية.', 'sort_order' => 10],
            ['name_ar' => 'الإضاءة', 'slug' => 'lighting', 'description_ar' => 'إضاءة منزلية بطابع دافئ ومتزن.', 'sort_order' => 20],
            ['name_ar' => 'الطاولات', 'slug' => 'tables', 'description_ar' => 'طاولات عملية بخامات طبيعية.', 'sort_order' => 30],
            ['name_ar' => 'المنسوجات', 'slug' => 'textiles', 'description_ar' => 'تفاصيل ناعمة تضيف الراحة والدفء.', 'sort_order' => 40],
            ['name_ar' => 'الديكور', 'slug' => 'decor', 'description_ar' => 'لمسات مختارة تكمل المساحة بهدوء.', 'sort_order' => 50],
        ])->mapWithKeys(function (array $data) {
            $category = Category::create($data);
            return [$data['slug'] => $category];
        });

        $attributeOptions = [];
        foreach ([
            'color' => [
                'name_ar' => 'اللون',
                'sort_order' => 10,
                'options' => [
                    ['code' => 'olive', 'value_ar' => 'زيتوني', 'sort_order' => 10],
                    ['code' => 'sand', 'value_ar' => 'رملي', 'sort_order' => 20],
                ],
            ],
            'finish' => [
                'name_ar' => 'تشطيب القاعدة',
                'sort_order' => 20,
                'options' => [
                    ['code' => 'dark-walnut', 'value_ar' => 'جوزي داكن', 'sort_order' => 10],
                    ['code' => 'natural-oak', 'value_ar' => 'بلوط طبيعي', 'sort_order' => 20],
                ],
            ],
        ] as $attributeCode => $attributeData) {
            $attribute = VariantAttribute::create([
                'code' => $attributeCode,
                'name_ar' => $attributeData['name_ar'],
                'sort_order' => $attributeData['sort_order'],
            ]);

            foreach ($attributeData['options'] as $optionData) {
                $option = VariantAttributeOption::create([
                    'variant_attribute_id' => $attribute->id,
                    'code' => $optionData['code'],
                    'value_ar' => $optionData['value_ar'],
                    'sort_order' => $optionData['sort_order'],
                ]);
                $attributeOptions[$attributeCode][$option->value_ar] = $option;
            }
        }

        $products = [
            [
                'category' => 'seating', 'name_ar' => 'كرسي استرخاء مخملي', 'slug' => 'olive-velvet-lounge-chair',
                'short' => 'قطعة ذات حضور هادئ وخطوط ناعمة، مصممة لتنسجم بصريًا مع مساحات المعيشة الدافئة.',
                'description' => 'كرسي استرخاء بملمس مخملي ظاهر وهيئة مستديرة، مع خيارات بيع فعلية للون وتشطيب القاعدة ضمن بيانات التطوير.',
                'details' => 'بيانات تطوير متعمدة لتجربة S04: اللون وتشطيب القاعدة يحددان Variant حقيقيًا، ولا تمثل هذه البيانات كتالوجًا إنتاجيًا.',
                'material' => 'مخمل', 'room' => 'المعيشة', 'featured' => true,
                'variants' => [
                    [
                        'sku' => 'BAS-CHAIR-OLV-01', 'name' => 'مخمل زيتوني · جوزي داكن', 'price' => 1950,
                        'inventory' => 25, 'default' => true, 'active' => true,
                        'attribute_values' => ['color' => 'زيتوني', 'finish' => 'جوزي داكن'],
                    ],
                    [
                        'sku' => 'BAS-CHAIR-SAND-01', 'name' => 'مخمل رملي · جوزي داكن', 'price' => 2050,
                        'inventory' => 18, 'default' => false, 'active' => true,
                        'attribute_values' => ['color' => 'رملي', 'finish' => 'جوزي داكن'],
                    ],
                    [
                        'sku' => 'BAS-CHAIR-OLV-OAK-01', 'name' => 'مخمل زيتوني · بلوط طبيعي', 'price' => 1980,
                        'inventory' => 12, 'default' => false, 'active' => true,
                        'attribute_values' => ['color' => 'زيتوني', 'finish' => 'بلوط طبيعي'],
                    ],
                    [
                        'sku' => 'BAS-CHAIR-SAND-OAK-01', 'name' => 'مخمل رملي · بلوط طبيعي', 'price' => 2080,
                        'inventory' => 0, 'default' => false, 'active' => false,
                        'attribute_values' => ['color' => 'رملي', 'finish' => 'بلوط طبيعي'],
                    ],
                ],
                'media' => [
                    ['images/products/chair-main.jpg', 'كرسي استرخاء مخملي بلون زيتوني', 0],
                    ['images/products/chair-detail-side.jpg', 'منظر جانبي لكرسي الاسترخاء المخملي', 1],
                    ['images/products/chair-detail-seat.jpg', 'تفصيل المقعد والخامة المخملية', 2],
                    ['images/products/chair-detail-back.jpg', 'تفصيل ظهر كرسي الاسترخاء', 3],
                ],
            ],
            [
                'category' => 'lighting', 'name_ar' => 'مصباح طاولة سيراميك', 'slug' => 'ceramic-table-lamp',
                'short' => 'إضاءة هادئة بقاعدة سيراميك مطفية وتكوين بسيط.',
                'description' => 'مصباح طاولة متزن يضيف إضاءة دافئة من دون حضور بصري صاخب.',
                'details' => 'قطعة إضاءة تجريبية ضمن بيانات التطوير فقط.',
                'material' => 'سيراميك', 'room' => 'غرفة النوم', 'featured' => true, 'sku' => 'BAS-LAMP-CER-01', 'variant' => 'سيراميك زيتوني', 'price' => 420,
                'media' => [['images/editorial/lighting.jpg', 'مصباح طاولة سيراميك ضمن مشهد إضاءة', 0]],
            ],
            [
                'category' => 'tables', 'name_ar' => 'طاولة قهوة خشب طبيعي', 'slug' => 'natural-wood-coffee-table',
                'short' => 'طاولة منخفضة بخط دائري وخشب داكن لمساحات الجلوس.',
                'description' => 'طاولة قهوة عملية ذات حضور بسيط وخامة خشبية دافئة.',
                'details' => 'بيانات عرض وتطوير وليست كتالوجًا إنتاجيًا.',
                'material' => 'خشب', 'room' => 'المعيشة', 'featured' => true, 'sku' => 'BAS-TABLE-WOOD-01', 'variant' => 'خشب داكن', 'price' => 1290,
                'media' => [['images/editorial/dining.jpg', 'طاولة قهوة خشب طبيعي ضمن مشهد طاولة', 0]],
            ],
            [
                'category' => 'textiles', 'name_ar' => 'وسادة نسيج محبوك', 'slug' => 'knitted-textile-cushion',
                'short' => 'وسادة محايدة بنسيج واضح تضيف طبقة مريحة للمساحة.',
                'description' => 'وسادة نسيج محبوك بلون رملي هادئ.',
                'details' => 'بيانات تجريبية للتطوير.',
                'material' => 'نسيج', 'room' => 'المعيشة', 'featured' => true, 'sku' => 'BAS-CUSHION-KNIT-01', 'variant' => 'نسيج رملي', 'price' => 195,
                'media' => [['images/editorial/living.jpg', 'وسادة نسيج محبوك ضمن مشهد معيشة', 0]],
            ],
            [
                'category' => 'seating', 'name_ar' => 'كرسي طعام خشبي', 'slug' => 'wood-dining-chair',
                'short' => 'كرسي طعام بخطوط واضحة وخامة طبيعية.', 'description' => 'كرسي بسيط لمائدة يومية هادئة.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'خشب', 'room' => 'الطعام والضيافة', 'featured' => false,
                'sku' => 'BAS-DINING-CHAIR-01', 'variant' => 'خشب طبيعي', 'price' => 680,
                'media' => [['images/editorial/dining.jpg', 'كرسي طعام خشبي ضمن مشهد مائدة', 0]],
            ],
            [
                'category' => 'lighting', 'name_ar' => 'وحدة إضاءة معلقة', 'slug' => 'black-pendant-light',
                'short' => 'إضاءة معلقة سوداء بهيئة دقيقة للمائدة.', 'description' => 'وحدة إضاءة معلقة ذات حضور رسومي هادئ.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'معدن', 'room' => 'الطعام والضيافة', 'featured' => false,
                'sku' => 'BAS-PENDANT-BLK-01', 'variant' => 'معدن أسود', 'price' => 540,
                'media' => [['images/editorial/lighting.jpg', 'وحدة إضاءة معلقة سوداء', 0]],
            ],
            [
                'category' => 'decor', 'name_ar' => 'مزهرية حجرية هادئة', 'slug' => 'stone-vase',
                'short' => 'مزهرية بسطح مطفي لتنسيقات بسيطة.', 'description' => 'قطعة ديكور ذات كتلة هادئة ولون محايد.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'حجر', 'room' => 'المعيشة', 'featured' => false,
                'sku' => 'BAS-VASE-STONE-01', 'variant' => 'حجر فاتح', 'price' => 360,
                'media' => [['images/editorial/seasonal.jpg', 'مزهرية حجرية ضمن تنسيق موسمي', 0]],
            ],
            [
                'category' => 'tables', 'name_ar' => 'طاولة جانبية مستديرة', 'slug' => 'round-side-table',
                'short' => 'طاولة جانبية صغيرة للمساحات الهادئة.', 'description' => 'طاولة جانبية ذات قاعدة بسيطة وسطح دائري.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'خشب', 'room' => 'غرفة النوم', 'featured' => false,
                'sku' => 'BAS-SIDE-TABLE-01', 'variant' => 'خشب جوزي', 'price' => 890,
                'media' => [['images/editorial/bedroom.jpg', 'طاولة جانبية ضمن غرفة نوم هادئة', 0]],
            ],
            [
                'category' => 'textiles', 'name_ar' => 'غطاء خفيف للكنبة', 'slug' => 'soft-sofa-throw',
                'short' => 'نسيج خفيف بطابع طبيعي وملمس ناعم.', 'description' => 'غطاء بسيط يضيف طبقة ملمسية إلى جلسة المعيشة.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'نسيج', 'room' => 'المعيشة', 'featured' => false,
                'sku' => 'BAS-THROW-SAND-01', 'variant' => 'نسيج رملي', 'price' => 250,
                'media' => [['images/editorial/living.jpg', 'غطاء نسيجي ضمن غرفة معيشة', 0]],
            ],
            [
                'category' => 'decor', 'name_ar' => 'كونسول حجري', 'slug' => 'stone-console',
                'short' => 'قطعة كبيرة بخط هادئ لمداخل المنزل.', 'description' => 'كونسول بسيط يوازن بين الكتلة والفراغ.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'حجر', 'room' => 'المدخل', 'featured' => false,
                'sku' => 'BAS-CONSOLE-STONE-01', 'variant' => 'حجر رملي', 'price' => 1650,
                'media' => [['images/editorial/hero.jpg', 'كونسول حجري ضمن مساحة منزلية دافئة', 0]],
            ],
        ];

        foreach ($products as $index => $data) {
            $product = Product::create([
                'category_id' => $categories[$data['category']]->id,
                'name_ar' => $data['name_ar'],
                'slug' => $data['slug'],
                'short_description_ar' => $data['short'],
                'description_ar' => $data['description'],
                'details_ar' => $data['details'],
                'material_ar' => $data['material'],
                'room_ar' => $data['room'],
                'is_featured' => $data['featured'],
                'published_at' => now()->subDays(20 - $index),
            ]);

            $variants = $data['variants'] ?? [[
                'sku' => $data['sku'],
                'name' => $data['variant'],
                'price' => $data['price'],
                'inventory' => 25 + $index,
                'default' => true,
                'active' => true,
                'attribute_values' => [],
            ]];

            foreach ($variants as $variantData) {
                $variant = Variant::create([
                    'product_id' => $product->id,
                    'sku' => $variantData['sku'],
                    'name_ar' => $variantData['name'],
                    'price' => $variantData['price'],
                    'currency' => 'SAR',
                    'inventory_quantity' => $variantData['inventory'],
                    'is_default' => $variantData['default'],
                    'is_active' => $variantData['active'],
                ]);

                $optionIds = collect($variantData['attribute_values'])
                    ->map(function (string $value, string $attributeCode) use ($attributeOptions): int {
                        return $attributeOptions[$attributeCode][$value]->id;
                    })
                    ->values()
                    ->all();

                $variant->attributeOptions()->sync($optionIds);
            }

            foreach ($data['media'] as [$path, $alt, $sortOrder]) {
                ProductMedia::create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'alt_ar' => $alt,
                    'sort_order' => $sortOrder,
                ]);
            }
        }
    }
}
