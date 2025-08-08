<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-xl font-semibold">Imágenes</h3>
            <div class="flex items-center space-x-2 text-sm">
                <span
                    v-if="isSaving"
                    class="inline-flex items-center text-orange-600"
                >
                    <svg class="animate-spin h-4 w-4 mr-1" viewBox="0 0 24 24">
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                        ></path>
                    </svg>
                    Guardando orden...
                </span>
                <span v-else-if="saveState === 'success'" class="text-green-600"
                    >Orden guardado</span
                >
                <span v-else-if="saveState === 'error'" class="text-red-600"
                    >Error al guardar</span
                >
            </div>
        </div>

        <div
            ref="grid"
            class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4"
            @dragover.prevent
        >
            <div
                v-for="(img, index) in localImages"
                :key="img.id"
                class="group relative rounded-lg overflow-hidden bg-white border border-gray-200 shadow-sm hover:shadow-md transition-shadow cursor-move"
                draggable="true"
                @dragstart="onDragStart(index, $event)"
                @dragenter.prevent="onDragEnter(index)"
                @drop.prevent="onDrop(index)"
                @dragend="onDragEnd"
            >
                <div
                    class="absolute top-2 left-2 bg-black/60 text-white text-xs px-2 py-0.5 rounded-full"
                >
                    {{ index + 1 }}
                </div>
                <div
                    class="absolute top-2 right-2 hidden group-hover:flex items-center space-x-1"
                >
                    <button
                        type="button"
                        class="p-1 bg-white/90 rounded hover:bg-white shadow"
                        title="Mover"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            class="w-4 h-4 text-gray-700"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M5.47 3.97a.75.75 0 011.06 0L12 9.44l5.47-5.47a.75.75 0 111.06 1.06L13.06 10.5l5.47 5.47a.75.75 0 11-1.06 1.06L12 11.56l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47-5.47-5.47a.75.75 0 010-1.06z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="p-1 bg-white/90 rounded hover:bg-white shadow"
                        title="Eliminar"
                        @click="confirmDelete(img, index)"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            class="w-4 h-4 text-red-600"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M16.5 4.478v.227a48.816 48.816 0 013.878.512.75.75 0 11-.256 1.478l-.209-.035-1.005 13.07a3 3 0 01-2.991 2.77H8.084a3 3 0 01-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 01-.256-1.478A48.567 48.567 0 017.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 013.369 0c1.603.051 2.815 1.387 2.815 2.951zm-6.136-1.452a51.196 51.196 0 013.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 00-6 0v-.113c0-.794.609-1.428 1.364-1.452zm-.355 5.945a.75.75 0 10-1.5.058l.347 9a.75.75 0 101.499-.058l-.346-9zm5.48.058a.75.75 0 10-1.498-.058l-.347 9a.75.75 0 001.5.058l.345-9z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </button>
                </div>
                <div
                    class="aspect-square bg-gray-100 flex items-center justify-center"
                >
                    <img :src="img.url" class="w-full h-full object-contain" />
                </div>
            </div>
        </div>

        <transition name="fade">
            <div
                v-if="toast.show"
                class="fixed bottom-6 right-6 bg-gray-900 text-white rounded-lg shadow-lg px-4 py-2 text-sm"
            >
                {{ toast.message }}
            </div>
        </transition>
    </div>
</template>

<script>
export default {
    name: "ProductImageReorder",
    props: {
        images: { type: Array, required: true },
        reorderUrl: { type: String, required: true },
        csrf: { type: String, required: true },
    },
    data() {
        return {
            localImages: this.images.slice(),
            dragFromIndex: null,
            isSaving: false,
            saveState: null, // null | 'success' | 'error'
            toast: { show: false, message: "" },
        };
    },
    methods: {
        onDragStart(index, e) {
            this.dragFromIndex = index;
            e.dataTransfer.effectAllowed = "move";
        },
        onDragEnter(index) {
            // visual handled by CSS hover; logic on drop
        },
        onDrop(index) {
            if (this.dragFromIndex === null || this.dragFromIndex === index)
                return;
            const moved = this.localImages.splice(this.dragFromIndex, 1)[0];
            this.localImages.splice(index, 0, moved);
            this.dragFromIndex = null;
            this.persistOrder();
        },
        onDragEnd() {
            this.dragFromIndex = null;
        },
        async persistOrder() {
            try {
                this.isSaving = true;
                this.saveState = null;
                const order = this.localImages.map((i) => i.id);
                const res = await fetch(this.reorderUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": this.csrf,
                        Accept: "application/json",
                    },
                    body: JSON.stringify({ order }),
                });
                if (!res.ok) throw new Error("failed");
                this.saveState = "success";
                this.showToast("Orden guardado");
            } catch (e) {
                this.saveState = "error";
                this.showToast("No se pudo guardar el orden");
            } finally {
                this.isSaving = false;
                setTimeout(() => (this.saveState = null), 1500);
            }
        },
        async confirmDelete(img, index) {
            if (!confirm("¿Eliminar esta imagen?")) return;
            try {
                const res = await fetch(img.delete_url, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": this.csrf,
                        "X-HTTP-Method-Override": "DELETE",
                        Accept: "application/json",
                    },
                });
                if (!res.ok) throw new Error("failed");
                this.localImages.splice(index, 1);
                await this.persistOrder();
                this.showToast("Imagen eliminada");
            } catch (e) {
                this.showToast("No se pudo eliminar la imagen");
            }
        },
        showToast(message) {
            this.toast.message = message;
            this.toast.show = true;
            setTimeout(() => (this.toast.show = false), 1800);
        },
    },
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
