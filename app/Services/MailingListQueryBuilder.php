<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\MailingList;
use App\Models\MailingListFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MailingListQueryBuilder
{
    public static function build(MailingList $list): Builder
    {
        if ($list->raw_where) {
            return static::buildAdvanced($list);
        }

        return static::buildSimple($list);
    }

    // ── Simple mode ───────────────────────────────────────────────────────────

    private static function buildSimple(MailingList $list): Builder
    {
        $query = Contact::query()
            ->where('do_not_contact', false)
            ->where('mailing_list_opt_in', true);

        foreach ($list->filters as $filter) {
            if ($list->conjunction === 'or') {
                $query->orWhere(function (Builder $q) use ($filter) {
                    static::applyFilter($q, $filter);
                });
            } else {
                $query->where(function (Builder $q) use ($filter) {
                    static::applyFilter($q, $filter);
                });
            }
        }

        return $query;
    }

    private static function applyFilter(Builder $query, MailingListFilter $filter): void
    {
        $field    = $filter->field;
        $operator = $filter->operator;
        $value    = $filter->value;

        match ($operator) {
            'equals'      => $query->where($field, $value),
            'not_equals'  => $query->where($field, '!=', $value),
            'contains'    => $query->where($field, 'ilike', "%{$value}%"),
            'not_contains'=> $query->where($field, 'not ilike', "%{$value}%"),
            'includes'    => $query->whereHas('tags', fn ($q) => $q->where('name', $value)),
            'not_includes'=> $query->whereDoesntHave('tags', fn ($q) => $q->where('name', $value)),
            'is_empty'    => $query->where(fn ($q) => $q->whereNull($field)->orWhere($field, '')),
            'is_not_empty'=> $query->where(fn ($q) => $q->whereNotNull($field)->where($field, '!=', '')),
            default       => null,
        };
    }

    // ── Advanced mode ─────────────────────────────────────────────────────────

    private static function buildAdvanced(MailingList $list): Builder
    {
        static::validateRawWhere($list->raw_where);

        try {
            $results = DB::connection('pgsql_readonly')->transaction(function () use ($list) {
                DB::connection('pgsql_readonly')->statement("SET LOCAL statement_timeout = '5000'");
                return DB::connection('pgsql_readonly')->select(
                    "SELECT id FROM contacts WHERE ({$list->raw_where}) LIMIT 50000"
                );
            });
        } catch (\Exception $e) {
            throw new \RuntimeException('Advanced filter error: ' . $e->getMessage());
        }

        $ids = collect($results)->pluck('id')->toArray();

        return Contact::query()
            ->where('do_not_contact', false)
            ->where('mailing_list_opt_in', true)
            ->whereIn('id', $ids);
    }

    private static function validateRawWhere(string $clause): void
    {
        $blocklist = [
            'drop', 'delete', 'update', 'insert', 'truncate',
            'alter', 'create', 'grant', 'revoke', 'execute', 'call',
            '--', '/*',
        ];

        $lower = strtolower($clause);

        foreach ($blocklist as $keyword) {
            if (str_contains($lower, $keyword)) {
                throw new \RuntimeException(
                    "The WHERE clause contains a prohibited keyword: \"{$keyword}\". Raw SQL mutations are not allowed."
                );
            }
        }
    }
}
