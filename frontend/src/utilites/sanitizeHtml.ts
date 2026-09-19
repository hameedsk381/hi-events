const ALLOWED_TAGS = new Set([
    'A', 'B', 'BR', 'CODE', 'DIV', 'EM', 'I', 'LI', 'OL', 'P', 'PRE', 'SPAN', 'STRONG', 'UL'
]);

export const sanitizeHtml = (value: string | null | undefined): string => {
    if (!value) return '';

    if (typeof window === 'undefined') {
        return value
            .replace(/<[^>]*>/g, '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    const parsed = new DOMParser().parseFromString(value, 'text/html');
    parsed.querySelectorAll('script,style,iframe,object,embed,form,svg,math').forEach((element) => element.remove());

    parsed.body.querySelectorAll('*').forEach((element) => {
        if (!ALLOWED_TAGS.has(element.tagName)) {
            element.replaceWith(...Array.from(element.childNodes));
            return;
        }

        Array.from(element.attributes).forEach((attribute) => {
            const name = attribute.name.toLowerCase();
            const attributeValue = attribute.value.trim().toLowerCase();

            if (name.startsWith('on') || (name === 'href' && !/^(https?:|mailto:)/.test(attributeValue))) {
                element.removeAttribute(attribute.name);
            }
        });
    });

    return parsed.body.innerHTML;
};
