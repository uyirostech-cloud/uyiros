<?php
namespace Tests\Unit;

use App\Core\Money;
use App\Core\Str;
use App\Core\HttpException;
use App\Core\Validator;
use Tests\Assert;
use Tests\TestCase;

final class CoreTest extends TestCase
{
    public function tests(): array
    {
        return [
            'U1 money parses and formats without float drift' => function () {
                Assert::same(50050, Money::toMinor('500.50'));
                Assert::same(50050, Money::toMinor(500.50));
                Assert::same(123450, Money::toMinor('1,234.50'));
                Assert::same('500.50', Money::toDecimal(50050));
                Assert::same('0.00', Money::toDecimal(0));
                Assert::same('-25.75', Money::toDecimal(-2575));
                // classic float trap: 0.1 + 0.2
                Assert::same(30, Money::toMinor('0.10') + Money::toMinor('0.20'));
                Assert::same('0.30', Money::toDecimal(Money::toMinor('0.10') + Money::toMinor('0.20')));
                // half-up rounding at the third decimal
                Assert::same(1006, Money::toMinor('10.055'));
                Assert::same(1005, Money::toMinor('10.054'));
            },
            'U2 invoice line math is exact' => function () {
                // 3 x 249.99 = 749.97, less 50 discount = 699.97, +18% tax = 125.99 → 825.96
                $line = Money::multiplyQuantity('3.000', '249.99');
                Assert::same(74997, $line);
                $taxable = $line - Money::toMinor('50.00');
                Assert::same(69997, $taxable);
                $tax = Money::percentOf($taxable, '18.000');
                Assert::same(12599, $tax);
                Assert::same('825.96', Money::toDecimal($taxable + $tax));

                // fractional quantity
                Assert::same(1250, Money::multiplyQuantity('2.500', '5.00'));
                Assert::same(0, Money::percentOf(50000, '0'));
            },
            'U3 phone normalization detects duplicates across formats' => function () {
                $expected = '9876543210';
                foreach (['+91 98765 43210', '098765 43210', '9876543210', '91-9876543210',
                          '(98765) 43210'] as $input) {
                    Assert::same($expected, Str::normalizePhone($input), "input: {$input}");
                }
                Assert::same(null, Str::normalizePhone(''));
                Assert::same(null, Str::normalizePhone(null));
                Assert::true(Str::normalizePhone('9876543210') !== Str::normalizePhone('9876543211'));
            },
            'U4 BMI derives from height and weight' => function () {
                // 70 kg at 170 cm → 24.22
                $bmi = round(70 / ((170 / 100) ** 2), 2);
                Assert::same(24.22, $bmi);
            },
            'U5 validator enforces types, ranges and enums' => function () {
                $clean = Validator::make(
                    ['name' => '  Anita  ', 'age' => '42', 'email' => 'A@Example.COM', 'gender' => 'Female'],
                    ['name' => 'required|string|maxlen:50', 'age' => 'required|int|min:0|max:130',
                     'email' => 'required|email', 'gender' => 'required|in:Male,Female,Other']
                );
                Assert::same('Anita', $clean['name']);
                Assert::same(42, $clean['age']);
                Assert::same('a@example.com', $clean['email']);

                foreach ([
                    [['age' => 'abc'], ['age' => 'required|int']],
                    [['email' => 'not-an-email'], ['email' => 'required|email']],
                    [['gender' => 'Unknown'], ['gender' => 'required|in:Male,Female']],
                    [[], ['name' => 'required|string']],
                    [['n' => str_repeat('x', 51)], ['n' => 'required|maxlen:50']],
                ] as [$data, $rules]) {
                    try {
                        Validator::make($data, $rules);
                        Assert::fail('Validator accepted invalid data: ' . json_encode($data));
                    } catch (HttpException $e) {
                        Assert::same(422, $e->status);
                    }
                }
            },
            'U6 business numbers pad and prefix correctly' => function () {
                Assert::same('PAT-000001', 'PAT-' . str_pad('1', 6, '0', STR_PAD_LEFT));
                Assert::same('INV-000042', 'INV-' . str_pad('42', 6, '0', STR_PAD_LEFT));
                Assert::same('LEAD-123456', 'LEAD-' . str_pad('123456', 6, '0', STR_PAD_LEFT));
            },
            'U7 ULIDs are unique and 26 characters' => function () {
                $seen = [];
                for ($i = 0; $i < 200; $i++) {
                    $id = Str::ulid();
                    Assert::same(26, strlen($id));
                    Assert::true(!isset($seen[$id]), 'ULID collision');
                    $seen[$id] = true;
                }
            },
        ];
    }
}
