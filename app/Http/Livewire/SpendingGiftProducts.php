<?php

namespace App\Http\Livewire;

use Livewire\Component;

class SpendingGiftProducts extends Component
{
    public $products = [];

    public $spendingGift;

    public $attached = [];

    public $selectedProduct;

    public function render()
    {
        $this->attached = $this->spendingGift->products()->orderBy('name')->get();

        return view('livewire.spending-gift-products');
    }

    public function add()
    {
        if ($this->selectedProduct) {
            $this->spendingGift->products()->syncWithoutDetaching([(int) $this->selectedProduct]);
            $this->selectedProduct = null;
        }
    }

    public function remove($id)
    {
        $this->spendingGift->products()->detach($id);
    }
}
