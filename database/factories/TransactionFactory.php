<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'type' => $this->faker->randomElement(['credit', 'debit']),
            'category' => $this->faker->randomElement([
                'funding', 'withdrawal', 'transfer_in', 'transfer_out', 'fee', 'reversal'
            ]),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'reference' => $this->faker->unique()->uuid(),
            'session_id' => $this->faker->uuid(),
            'status' => $this->faker->randomElement(['pending', 'completed', 'failed', 'reversed']),
            'status_code' => $this->faker->optional()->word(),
            'narration' => $this->faker->optional()->sentence(),
            'description' => $this->faker->optional()->paragraph(),
            'balance_before' => $this->faker->optional()->randomFloat(2, 1000, 50000),
            'balance_after' => $this->faker->optional()->randomFloat(2, 1000, 50000),
            'meta' => $this->faker->optional()->randomElements(),
        ];
    }
}