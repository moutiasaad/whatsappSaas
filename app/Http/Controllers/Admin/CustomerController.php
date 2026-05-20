<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::withCount('conversations')
            ->when($request->search, fn ($q, $s) =>
                $q->where('phone_e164', 'like', "%$s%")
                  ->orWhere('name', 'like', "%$s%")
            )
            ->orderByDesc('updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(Customer $customer)
    {
        $conversations = Conversation::with(['ownerAgent', 'instance'])
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.customers.show', compact('customer', 'conversations'));
    }
}
