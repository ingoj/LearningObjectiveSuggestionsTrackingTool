(function() {
  var accordionButtons = document.getElementsByClassName('tracking-tool-tree-icon');
  var i, newIconSrc = null;

  for (i = 0; i < accordionButtons.length; i++) {
    accordionButtons[i].addEventListener('click', function () {
      this.classList.toggle('tracking-tool-active');
      let action = this.getAttribute('data-action');

      if (action === 'expand') {
        newIconSrc = this.getAttribute('src').replace('tree_col', 'tree_exp');
        action = 'collapse';
      } else {
        newIconSrc = this.getAttribute('src').replace('tree_exp', 'tree_col');
        action = 'expand';
      }

      if (newIconSrc != null) {
        this.setAttribute('src', newIconSrc);
        this.setAttribute('data-action', action);
      }

      var panel = this.parentElement.nextElementSibling;
      if (panel.style.display === 'block') {
        panel.style.display = 'none';
      } else {
        panel.style.display = 'block';
      }
    });
  }
})();