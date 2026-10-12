import { ref } from 'vue';
import { postJson } from '../utils/fetchJson';
import { confirmDialog } from '../utils/confirmDialog';

const googleDoc = ref(null);
const googleDocsOpen = ref(false);
const googleDocsExporting = ref(false);

export function useGoogleDocsExport() {
    function openGoogleDoc(document) {
        googleDoc.value = document;
        googleDocsOpen.value = true;
    }

    function closeGoogleDocsModal() {
        googleDocsOpen.value = false;
    }

    async function exportToGoogleDocs(url, options = {}) {
        const {
            connectUrl = '/google/connect',
            profileUrl = '/profile',
            errorMessage = 'Не удалось сохранить документ в Google Docs.',
            connectMessage = 'Подключите Google в личном кабинете, чтобы сохранять документы в Google Docs.',
        } = options;

        if (!url) {
            return null;
        }

        googleDocsExporting.value = true;

        try {
            const { response, data } = await postJson(url, {});

            if (response.status === 409 && data?.needs_google_connect) {
                const goConnect = await confirmDialog({
                    title: 'Нужен Google-аккаунт',
                    message: data.message || connectMessage,
                    confirmLabel: 'Подключить Google',
                    cancelLabel: 'Отмена',
                    variant: 'primary',
                });

                if (goConnect) {
                    window.location.href = data.connect_url || connectUrl || profileUrl;
                }

                return null;
            }

            if (!response.ok || !data?.success || !data?.document) {
                throw new Error(data?.message || errorMessage);
            }

            openGoogleDoc(data.document);

            return data.document;
        } finally {
            googleDocsExporting.value = false;
        }
    }

    return {
        googleDoc,
        googleDocsOpen,
        googleDocsExporting,
        openGoogleDoc,
        closeGoogleDocsModal,
        exportToGoogleDocs,
    };
}
