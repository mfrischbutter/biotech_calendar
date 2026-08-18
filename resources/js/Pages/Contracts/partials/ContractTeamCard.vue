<script setup lang="ts">
import { Users } from 'lucide-vue-next';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { useTrans } from '@/lib/use-trans';
import { initials } from '@/lib/utils';

const { t } = useTrans();

defineProps<{
    /** Everyone ever booked on this contract. */
    team: { id: number; name: string }[];
}>();
</script>

<template>
    <Card data-testid="contract-team-card">
        <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
                <Users class="h-4 w-4 text-navy" />
                {{ t('Team') }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <ul v-if="team.length > 0" class="space-y-2">
                <li v-for="member in team" :key="member.id" class="flex items-center gap-2">
                    <Avatar class="h-7 w-7 bg-navy-wash">
                        <AvatarFallback class="bg-navy-wash text-[10px] font-semibold text-navy">
                            {{ initials(member.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="truncate text-sm text-foreground">{{ member.name }}</span>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">{{ t('Nobody assigned yet') }}</p>
        </CardContent>
    </Card>
</template>
