<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryAgent;
use App\Services\Inventory\InventoryIntelligence;
use Illuminate\Http\Request;

class InventoryIntelligenceController extends Controller
{
    public function __construct(
        private InventoryIntelligence $inventory,
        private InventoryAgent $agent,
    ) {}

    public function index(Request $request)
    {
        $window = $this->inventory->windowDays($request->input('window'));
        $cover = $this->inventory->coverDays($request->input('cover'));

        return view('admin.inventory.index', [
            'summary' => $this->inventory->summary($window, $cover, $request->string('bodega')->toString() ?: null),
            'rows' => $this->inventory->stock([
                'q' => $request->string('q')->toString(),
                'bodega' => $request->string('bodega')->toString(),
                'status' => $request->string('status')->toString(),
            ]),
            'warehouses' => $this->inventory->warehouseCodes(),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'bodega' => $request->string('bodega')->toString(),
                'status' => $request->string('status')->toString() ?: 'all',
            ],
        ]);
    }

    public function intelligence(Request $request)
    {
        $window = $this->inventory->windowDays($request->input('window'));
        $cover = $this->inventory->coverDays($request->input('cover'));
        $bodega = $request->string('bodega')->toString() ?: null;

        return view('admin.inventory.intelligence', [
            'summary' => $this->inventory->summary($window, $cover, $bodega),
            'reorder' => $this->inventory->reorder($bodega, $window, $cover),
            'dead' => $this->inventory->deadStock($bodega, $window, $cover),
            'movers' => $this->inventory->movers($bodega, $window, $cover),
            'warehouses' => $this->inventory->warehouseCodes(),
            'windowDays' => $window,
            'coverDays' => $cover,
            'bodega' => $bodega,
        ]);
    }

    public function agent()
    {
        return view('admin.inventory.agent', [
            'messages' => session('inventory_agent_history', []),
            'modelEnabled' => filled(config('services.openai.key')),
        ]);
    }

    public function ask(Request $request)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        $history = session('inventory_agent_history', []);
        $result = $this->agent->reply($data['message'], is_array($history) ? $history : []);

        $history = is_array($history) ? $history : [];
        $history[] = ['role' => 'user', 'content' => $data['message']];
        $history[] = ['role' => 'assistant', 'content' => $result['reply'], 'mode' => $result['mode']];
        session(['inventory_agent_history' => array_slice($history, -10)]);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->route('admin.inventory.agent');
    }

    public function clearAgent()
    {
        session()->forget('inventory_agent_history');

        return redirect()->route('admin.inventory.agent');
    }
}
