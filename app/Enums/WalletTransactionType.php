<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case CASHBACK_REWARD = 'cashback_reward';
    case WALLET_TOPUP = 'wallet_topup';
    case ORDER_PAYMENT = 'order_payment';
    case REFUND_CREDIT = 'refund_credit';
    case REFERRAL_REWARD = 'referral_reward';
    case WITHDRAWAL = 'withdrawal';
}
