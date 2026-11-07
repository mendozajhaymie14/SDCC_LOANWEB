<?php

namespace App\Console\Commands;

use App\Models\CoopMember;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SdccLinkMembers extends Command
{
    protected $signature = 'sdcc:link-members {--apply : Persist the links instead of a dry-run}';
    protected $description = 'Link users with an empty coop_member_id to CoopMember rows whose user_id points at them, and vice versa';

    public function handle(): int
    {
        $apply = $this->option('apply');

        if ($apply) {
            $this->warn('APPLY MODE - links will be persisted');
        } else {
            $this->warn('DRY RUN MODE - no changes will be persisted');
        }

        $this->info('Scanning for unlinked user/member pairs...');

        // Users with a coop_member_id that does not point at an existing CoopMember
        $orphanUsers = User::whereNotNull('coop_member_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('coop_members')->whereColumn('coop_members.id', 'users.coop_member_id');
            })
            ->get();

        // CoopMembers with a user_id that does not point back at them (user.coop_member_id is null or points elsewhere)
        $orphanMembers = CoopMember::whereNotNull('user_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('users')->whereColumn('users.id', 'coop_members.user_id')->whereColumn('users.coop_member_id', 'coop_members.id');
            })
            ->get();

        // Users with coop_member_id empty but a CoopMember row whose user_id points at them
        $unlinkedUsers = User::where(function ($q) {
            $q->whereNull('coop_member_id')->orWhere('coop_member_id', '');
        })
            ->whereIn('id', function ($q) {
                $q->select('user_id')->from('coop_members')->whereNotNull('user_id');
            })
            ->get();

        $planned = [];

        foreach ($orphanUsers as $user) {
            $planned[] = [
                'type'  => 'orphan_user_coop_member_id',
                'user'  => $user->name . ' (' . $user->email . ')',
                'user_id' => $user->id,
                'coop_member_id' => $user->coop_member_id,
                'fix'   => "Unset coop_member_id on user id={$user->id} (#{$user->name})",
            ];
        }

        foreach ($orphanMembers as $member) {
            $planned[] = [
                'type'   => 'orphan_member_user_id',
                'member' => $member->full_name . ' (' . $member->member_id . ')',
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'fix'    => "Unset user_id on CoopMember id={$member->id} (#{$member->full_name})",
            ];
        }

        foreach ($unlinkedUsers as $user) {
            $member = CoopMember::where('user_id', $user->id)->first();
            if (! $member) {
                continue;
            }
            $planned[] = [
                'type'   => 'link_user_to_member',
                'user'   => $user->name . ' (' . $user->email . ')',
                'user_id' => $user->id,
                'member' => $member->full_name . ' (' . $member->member_id . ')',
                'member_id' => $member->id,
                'fix'    => "Set user id={$user->id} coop_member_id={$member->id}; set CoopMember id={$member->id} user_id={$user->id}",
            ];
        }

        if (empty($planned)) {
            $this->info('No unlinked or orphaned pairs found. Everything is consistent.');
            return self::SUCCESS;
        }

        $this->info("Found " . count($planned) . " item(s) to review:");
        $this->newLine();

        foreach ($planned as $item) {
            $this->line("<info>{$item['type']}</info>");
            $this->line("  user:   {$item['user'] ?? '—'} (id={$item['user_id'] ?? '—'})");
            $this->line("  member: {$item['member'] ?? '—'} (id={$item['member_id'] ?? '—'})");
            $this->line("  coop_member_id: {$item['coop_member_id'] ?? '—'}");
            $this->line("  fix:    {$item['fix']}");
            $this->newLine();
        }

        if (! $apply) {
            $this->info("Re-run with --apply to persist these changes.");
            return self::SUCCESS;
        }

        $applied = 0;
        DB::transaction(function () use ($planned, &$applied) {
            foreach ($planned as $item) {
                if ($item['type'] === 'orphan_user_coop_member_id') {
                    $user = User::find($item['user_id']);
                    if ($user) {
                        $user->coop_member_id = null;
                        $user->save();
                        $applied++;
                    }
                } elseif ($item['type'] === 'orphan_member_user_id') {
                    $member = CoopMember::find($item['member_id']);
                    if ($member) {
                        $member->user_id = null;
                        $member->save();
                        $applied++;
                    }
                } elseif ($item['type'] === 'link_user_to_member') {
                    $user = User::find($item['user_id']);
                    $member = CoopMember::find($item['member_id']);
                    if ($user && $member) {
                        $user->coop_member_id = $member->id;
                        $user->save();
                        $member->user_id = $user->id;
                        $member->save();
                        $applied++;
                    }
                }
            }
        });

        $this->info("Applied {$applied} link(s).");
        return self::SUCCESS;
    }
}
