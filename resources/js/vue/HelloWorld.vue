<template>
    <div class="hello-world">
        <h2>{{ greeting }}</h2>
        <button
            @click="updateGreeting"
            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
        >
            Change Greeting
        </button>
    </div>
</template>

<script setup>
import { ref } from "vue";

const greeting = ref("Hello World!");

const updateGreeting = () => {
    greeting.value = "Hello Vue in Laravel!";
};
</script>

<style scoped>
.hello-world {
    padding: 2rem;
    text-align: center;
}

h2 {
    color: #2c3e50;
    margin-bottom: 1rem;
}
</style>
