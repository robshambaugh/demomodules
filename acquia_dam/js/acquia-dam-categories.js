(function (Drupal) {
  const categoryCache = {};
  class WidenCategories extends HTMLElement {
    constructor() {
      super()
      const wrapper = document.createElement('div');
      wrapper.classList.add('container-inline', 'container-dropdown')
      wrapper.innerHTML = `
<div class="category-dropdown">
    <ul class="widen-category-menu category-dropdown-inner menu" hidden></ul>
</div>`;

      this.showMenu = this.showMenu.bind(this)
      this.buildMenu = this.buildMenu.bind(this)
      this.doRequest = this.doRequest.bind(this)
      this.closeOnOutsideClick = this.closeOnOutsideClick.bind(this)
      this.wrapper = wrapper;
    }

    connectedCallback() {
      this.appendChild(this.wrapper)

      this.menu = this.wrapper.querySelector('.widen-category-menu');
      this.value = this.querySelector('input[type="hidden"]')
      this.placeholder = this.querySelector('input[type="text"]')
      this.placeholder.addEventListener('click', this.showMenu, false)
      this.buildMenu(this.value.value)
    }

    disconnectedCallback() {
      this.placeholder.removeEventListener('click', this.showMenu, false)
    }

    buildMenu(path) {
      const self = this;
      this.doRequest(path)
        .then(json => {
          this.menu.replaceChildren()
          const pathArray = path.split('/').filter(String)
          if (pathArray.length > 0) {
            pathArray.pop();
            const backItem = document.createElement('li')
            backItem.dataset.path = pathArray.join('/')
            backItem.innerHTML = '<button type="button" class="category-link widen-categories-chevron-left">Back</button>'
            backItem.querySelector('button').addEventListener('click', this.expandCategory.bind(self))
            this.menu.appendChild(backItem)

            const category = path.split('/').filter(String)
            const selectAllItem = document.createElement('li')
            selectAllItem.dataset.path = path
            selectAllItem.dataset.crumbs = category.map(c => decodeURIComponent(c)).join(' > ');
            selectAllItem.innerHTML = '<button type="button" class="category-link">All ' + decodeURIComponent(category.pop()) + '</button>'
            selectAllItem.querySelector('button').addEventListener('click', this.selectCategory.bind(self))
            this.menu.appendChild(selectAllItem)
          }
          else {
            const selectAllCategory = document.createElement('li')
            selectAllCategory.dataset.path = ''
            selectAllCategory.dataset.crumbs = Drupal.t('All categories');
            selectAllCategory.innerHTML = '<button type="button" class="category-link">All</button>'
            selectAllCategory.querySelector('button').addEventListener('click', this.selectCategory.bind(self))
            this.menu.appendChild(selectAllCategory)
          }
          json.items.map(category => {
            const listItem = document.createElement('li')
            // There is a `path` property, but the values are not encoded
            // properly, and the API breaks without encoded values.
            const encodedPath = category.parts.map(part => encodeURIComponent(part)).join('/')
            listItem.classList.add()
            listItem.dataset.path = encodedPath;
            listItem.dataset.crumbs = category.parts.join(' > ');
            listItem.innerHTML = '<button type="button" class="category-link">' + category.name + '</button>';
            this.menu.appendChild(listItem)
            this.doRequest(encodedPath)
              .then(json => {
                if (json.total_count > 0) {
                  listItem.innerHTML = '<button type="button" class="category-link widen-categories-chevron-right">' + category.name + '</button>';
                  listItem.querySelector('button').addEventListener('click', this.expandCategory.bind(self))
                } else {
                  listItem.querySelector('button').addEventListener('click', this.selectCategory.bind(self))
                }
              })
          })
        });
    }

    showMenu() {
      if (this.menu.hidden) {
        window.addEventListener('mousedown', this.closeOnOutsideClick, false)
      } else {
        window.removeEventListener('mousedown', this.closeOnOutsideClick, false)
      }
      this.menu.hidden = !this.menu.hidden
    }

    closeOnOutsideClick(event) {
      if (!this.contains(event.target)) {
        this.menu.hidden = true;
      }
    }

    expandCategory(e) {
      this.buildMenu(e.target.parentNode.dataset.path)
    }

    selectCategory(e) {
      this.value.value = e.target.parentNode.dataset.path;
      this.placeholder.value = e.target.parentNode.dataset.crumbs
      this.showMenu();
    }

    doRequest(path) {
      if (!categoryCache.hasOwnProperty(path)) {
        categoryCache[path] = fetch(Drupal.url('acquia-dam/categories?category=' + path), {
          headers: {
            'Content-Type': 'application/json',
          }
        }).then(res => res.json())
      }
      return categoryCache[path];
    }
  }

  customElements.get('widen-categories') || customElements.define('widen-categories', WidenCategories)
})(Drupal)
