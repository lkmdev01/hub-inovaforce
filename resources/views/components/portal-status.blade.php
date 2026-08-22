@props(['status'])

@php
    [$label, $classes] = match ($status) {
        'active' => ['Ativa', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400'],
        'trialing' => ['Teste', 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400'],
        'pending' => ['Aguardando pagamento', 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400'],
        'past_due' => ['Pagamento falhou', 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400'],
        'paid' => ['Paga', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400'],
        'open' => ['Em aberto', 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400'],
        'overdue' => ['Vencida', 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400'],
        'draft' => ['Rascunho', 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-zinc-800 dark:text-zinc-300'],
        'canceled' => ['Cancelada', 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-zinc-800 dark:text-zinc-300'],
        'refunded' => ['Estornada', 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-500/10 dark:text-orange-400'],
        'refund_pending' => ['Estorno em andamento', 'bg-orange-50 text-orange-700 ring-orange-600/20 dark:bg-orange-500/10 dark:text-orange-400'],
        'chargeback' => ['Chargeback', 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400'],
        'failed', 'error' => ['Falhou', 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400'],
        'authorized' => ['Autorizada', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400'],
        'scheduled', 'queued' => ['Agendada', 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400'],
        'synchronized' => ['Em processamento', 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400'],
        'resolved' => ['Resolvido', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400'],
        'sent' => ['Enviado', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400'],
        'waiting_configuration' => ['Aguardando configuração', 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400'],
        'suspended' => ['Suspenso', 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400'],
        'revoked' => ['Revogado', 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-zinc-800 dark:text-zinc-300'],
        default => [str($status)->headline(), 'bg-zinc-100 text-zinc-600 ring-zinc-500/20'],
    };
@endphp

<span {{ $attributes->class("inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset $classes") }}>{{ $label }}</span>
