<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

withDefaults(
    defineProps<{
        name: string;
        label: string;
        value?: string | number | null;
        error?: string;
        type?: string;
        required?: boolean;
        readonly?: boolean;
        min?: string | number;
        max?: string | number;
        step?: string | number;
    }>(),
    {
        value: null,
        error: undefined,
        type: 'text',
        required: false,
        readonly: false,
        min: undefined,
        max: undefined,
        step: undefined,
    },
);
</script>

<template>
    <div class="grid min-w-0 gap-1.5">
        <Label :for="name" class="text-[11px] font-semibold text-stone-700">
            {{ label }}
        </Label>
        <Input
            :id="name"
            :name="name"
            :type="type"
            :default-value="value ?? undefined"
            :required="required"
            :readonly="readonly"
            :min="min"
            :max="max"
            :step="step"
            class="h-9 rounded-none border-x-0 border-t-0 border-b-stone-400 bg-transparent px-1 shadow-none focus-visible:ring-0"
        />
        <InputError :message="error" />
    </div>
</template>
