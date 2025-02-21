export const getClass = (cls: string) => {
    const documentStyle = getComputedStyle(document.documentElement);
    return documentStyle.getPropertyValue(cls);;
};