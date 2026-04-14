export default (state, groups, allPerms, resources) => ({
    state,
    perms: groups,
    allPerms,
    resources,

    hasGroup(resource, group) {
        const current = this.state || [];
        return this.perms[group].every(action => current.includes(action + '_' + resource));
    },

    toggleGroup(resource, group) {
        const actions = this.perms[group];
        const current = [...(this.state || [])];
        const allChecked = actions.every(a => current.includes(a + '_' + resource));
        if (allChecked) {
            const drop = actions.map(a => a + '_' + resource);
            this.state = current.filter(p => !drop.includes(p));
        } else {
            const add = actions.map(a => a + '_' + resource).filter(p => !current.includes(p));
            this.state = [...current, ...add];
        }
    },

    columnAllChecked(group) {
        return this.resources.every(r => this.hasGroup(r, group));
    },

    toggleColumn(group) {
        if (this.columnAllChecked(group)) {
            this.resources.forEach(r => {
                const drop = this.perms[group].map(a => a + '_' + r);
                this.state = (this.state || []).filter(p => !drop.includes(p));
            });
        } else {
            this.resources.forEach(r => {
                const add = this.perms[group].map(a => a + '_' + r).filter(p => !(this.state || []).includes(p));
                this.state = [...(this.state || []), ...add];
            });
        }
    },

    selectAll() {
        const existing = (this.state || []).filter(p => !this.allPerms.includes(p));
        this.state = [...existing, ...this.allPerms];
    },

    clearAll() {
        this.state = (this.state || []).filter(p => !this.allPerms.includes(p));
    },
});
