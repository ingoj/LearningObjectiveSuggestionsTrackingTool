(function() {
  var accordionButtons = document.getElementsByClassName('tracking-tool-tree-icon');
  var i, newIconSrc = null;

  function initialLoad(accordionButton) {
    var accordionButtonDisplay = 'none',
        accordionButtonAction = 'expand';

    if (localStorage.getItem(accordionButton.parentElement.nextElementSibling.id) === null) {
      localStorage.setItem(accordionButton.parentElement.nextElementSibling.id, 'true');
      accordionButtonDisplay = 'block';
    } else if (localStorage.getItem(accordionButton.parentElement.nextElementSibling.id) === 'true') {
      accordionButtonDisplay = 'block';
      accordionButtonAction = 'collapse';
      newIconSrc = accordionButton.getAttribute('src').replace('tree_col', 'tree_exp');
      accordionButton.setAttribute('src', newIconSrc);
    } else {
      newIconSrc = accordionButton.getAttribute('src').replace('tree_exp', 'tree_col');
      accordionButton.setAttribute('src', newIconSrc);
    }
    accordionButton.parentElement.nextElementSibling.style.display = accordionButtonDisplay;
    accordionButton.setAttribute('data-action', accordionButtonAction);
  }

  for (i = 0; i < accordionButtons.length; i++) {

    initialLoad(accordionButtons[i]);

    accordionButtons[i].addEventListener('click', function () {
      var panel = this.parentElement.nextElementSibling;

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

      if (panel.style.display === 'none') {
        panel.style.display = 'block';
        localStorage.setItem(panel.id, 'true');
      } else {
        panel.style.display = 'none';
        localStorage.setItem(panel.id, 'false');
      }
    });
  }
})();