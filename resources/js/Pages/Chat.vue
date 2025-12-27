<script setup>
import { ref } from 'vue';

const inputMessage = ref('');
const messages = ref([]);
const isLoading = ref(false);
const previousResponseId = ref(null);

const sendMessage = async () => {
    if (!inputMessage.value.trim() || isLoading.value) return;

    const userMessage = inputMessage.value.trim();
    messages.value.push({ content: userMessage, isUser: true });
    inputMessage.value = '';
    isLoading.value = true;

    try {
        const payload = { message: userMessage };
        if (previousResponseId.value) {
            payload.previous_response_id = previousResponseId.value;
        }

        const response = await fetch('/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${import.meta.env.VITE_CHAT_API_TOKEN || ''}`,
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            messages.value.push({
                content: `エラー: ${data?.error || 'リクエストに失敗しました'}`,
                isUser: false,
            });
            return;
        }

        messages.value.push({
            content: data?.message || '応答がありませんでした',
            isUser: false,
        });

        if (data?.response_id) {
            previousResponseId.value = data.response_id;
        }
    } catch (error) {
        messages.value.push({
            content: `エラー: ${error?.message || '通信に失敗しました'}`,
            isUser: false,
        });
    } finally {
        isLoading.value = false;
    }
};
</script>    

<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="w-full max-w-2xl bg-white rounded-lg shadow-lg h-[600px] flex flex-col">
            <!-- Header -->
            <div class="border-b border-gray-200 p-4">
                <h1 class="text-xl font-semibold text-gray-800">Movacal Chat</h1>
                <p class="text-sm text-gray-500">モバカルの情報を参照できます</p>
            </div>

            <!-- Messages Area -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <div v-if="messages.length === 0" class="flex justify-center items-center h-full">
                    <p class="text-gray-400">メッセージを入力してください</p>
                </div>
                <div
                    v-for="(message, index) in messages"
                    :key="index"
                    :class="[
                        'flex',
                        message.isUser ? 'justify-end' : 'justify-start'
                    ]"
                >
                    <div
                        :class="[
                            'max-w-[80%] rounded-lg p-3',
                            message.isUser
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 text-gray-800'
                        ]"
                    >
                        <p class="whitespace-pre-wrap">{{ message.content }}</p>
                    </div>
                </div>
                <div v-if="isLoading" class="flex justify-start">
                    <div class="bg-gray-100 rounded-lg p-3">
                        <div class="flex gap-1">
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="border-t border-gray-200 p-4">
                <form @submit.prevent="sendMessage" class="flex gap-2">
                    <input
                        v-model="inputMessage"
                        type="text"
                        placeholder="メッセージを入力..."
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :disabled="isLoading"
                    >
                    <button
                        type="submit"
                        :disabled="!inputMessage.trim() || isLoading"
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        送信
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
    