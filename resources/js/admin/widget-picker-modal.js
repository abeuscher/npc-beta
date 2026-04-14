export default (initialCategory = '') => ({
    picked: false,
    filter: '',
    activeCategory: initialCategory,
    matchesFilter(label, desc) {
        if (this.filter === '') return true;
        const q = this.filter.toLowerCase();
        return label.toLowerCase().includes(q) || (desc && desc.toLowerCase().includes(q));
    },
    matchesCategory(cats) {
        if (this.activeCategory === '') return true;
        return cats.includes(this.activeCategory);
    },
});
