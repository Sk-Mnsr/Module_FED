export const OD_MAX_UPLOAD_MO = 25;
export const OD_MAX_UPLOAD_BYTES = OD_MAX_UPLOAD_MO * 1024 * 1024;

export function odFileTooLarge(file: File | null | undefined, maxMo = OD_MAX_UPLOAD_MO): string | null {
    if (!file) {
        return null;
    }

    const maxBytes = maxMo * 1024 * 1024;
    if (file.size > maxBytes) {
        return `Fichier trop volumineux (max. ${maxMo} Mo).`;
    }

    return null;
}
