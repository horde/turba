var TurbaTabs = {
    // Properties: current, tabs

    showTab: function(tab)
    {
        var next, id;

        this.tabs.forEach(function(item) {
            id = item.id.substring(3);
            var content = document.getElementById(id);
            if (id == tab) {
                item.classList.add('horde-active');
                if (content) {
                    content.style.display = '';
                }
                next = id;
            } else {
                item.classList.remove('horde-active');
                if (content && content.style.display != 'none' &&
                    typeof window['sections_Turba_View_' + id] !== 'undefined') {
                    this.current = window['sections_Turba_View_' + id]._get();
                }
                if (content) {
                    content.style.display = 'none';
                }
            }
        }, this);

        if (this.current &&
            typeof window['sections_Turba_View_' + next] !== 'undefined') {
            window['sections_Turba_View_' + next].toggle(this.current); // eslint-disable-line horde/no-prototype-methods -- Horde_Form_Sections.toggle()
        }

        return false;
    },

    onDomLoad: function() {
        var buttonbar = document.getElementById('page').querySelector('.horde-buttonbar');
        this.tabs = Array.from(buttonbar.firstElementChild.querySelectorAll('li'));
    }
};

document.addEventListener('DOMContentLoaded', TurbaTabs.onDomLoad.bind(TurbaTabs));
