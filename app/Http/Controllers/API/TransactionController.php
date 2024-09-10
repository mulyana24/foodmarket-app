<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Midtrans\Config;
use Midtrans\Snap;

class TransactionController extends Controller
{
    public function all(Request $request)
    {
        $id = $request->input('id');
        $limit = $request->input('limit', 6);
        // $user_id = $request->input('user_id');
        $food_id = $request->input('food_id');
        // $quantity = $request->input('quantity');
        // $total = $request->input('total');
        $status = $request->input('status');
        // $payment_url = $request->input('payment_url');

       
        if($id)
        {
            $transcation = Transaction::with(['food', 'user'])->find($id);

            if($transcation)
            {
                return ResponseFormatter::success(
                    $transcation,
                    'Data transaksi berhasil diambil'
                );
            } else {
                return ResponseFormatter::error(
                    null,
                    'Data transaksi tidak ada',
                    404
                );
            }
        }
        $transaction = Transaction::with(['food', 'user'])->where('user_id', Auth::user()->id);

        if($food_id)
        {
            $transaction->where('food_id', $food_id);
        }

        if($status)
        {
            $transaction->where('status', $status);
        }

        return ResponseFormatter::success(
            $transaction->paginate($limit),
            'Data list transaction berhasil diambil'
        );
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        $transaction->update($request->all());

        return ResponseFormatter::success($transaction, 'Transaksi berhasil diperbarui');
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'food_id' => 'required|exists:food,id',
            'quantity' => 'required',
            'total' => 'required',
            'status' => 'required'
        ]);

        $transaction = Transaction::create([
            'user_id' => $request->user_id,
            'food_id' => $request->food_id,
            'quantity' => $request->quantity,
            'total' => $request->total,
            'status' => $request->status,
            'payment_url' => ''
        ]);

        // konfigurasi midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        // panggil transaksi yang tadi dibuat
        $transaction = Transaction::with(['user','food'])->find($transaction->id);

        // membuat transaksi Midtrans
        $midtrans = [
            "transaction_details" => [
                "order_id" => $transaction->id,
                "gross_amount"=> (int) $transaction->total
            ],
            'customer_details' => [
                'frist_name' => $transaction->user->name,
                'email' => $transaction->user->email,
            ],
            'enabled_payments' =>['shopeepay','bank_transfer'],
            'vtweb'=> []
            ];

        // Memanggil Midtrans
        try {
            //ambil snap/halaman payment midrans
            $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;

            $transaction->payment_url = $paymentUrl;
            $transaction->save();

            // mengembalikan data ke api
            return ResponseFormatter::success($transaction, 'Transaction berhasil');

        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), 'Transaction gagal');
        }

    }
}
