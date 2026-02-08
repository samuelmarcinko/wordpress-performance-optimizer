(function () {
  const app = document.getElementById('ish-admin-app');
  const dataInput = document.getElementById('ish-project-data');
  if (!app || !dataInput) {
    return;
  }

  const state = window.ISHAdminData || {};
  const projectData = state.projectData || { scenes: [] };
  let currentSceneId = projectData.scenes[0] ? projectData.scenes[0].id : null;
  let currentHotspotId = null;
  let drawMode = 'select';
  let polygonDraft = [];
  let drawStart = null;

  const defaultHover = {
    fill: '#00aaff',
    fillOpacity: 0.3,
    stroke: '#0088cc',
  };

  const appTemplate = `
    <div class="ish-admin-layout">
      <div class="ish-sidebar">
        <div class="ish-sidebar-header">
          <h3>Scenes</h3>
          <button class="button button-secondary" id="ish-add-scene">Add Scene</button>
        </div>
        <ul class="ish-scenes" id="ish-scenes"></ul>
      </div>
      <div class="ish-main">
        <div class="ish-scene-header">
          <input type="text" class="ish-input" id="ish-scene-title" placeholder="Scene title" />
          <button class="button" id="ish-scene-image">Select Image</button>
        </div>
        <div class="ish-workspace">
          <div class="ish-canvas" id="ish-canvas">
            <img id="ish-scene-image-preview" alt="" />
            <svg id="ish-overlay" viewBox="0 0 1 1" preserveAspectRatio="none"></svg>
          </div>
        </div>
        <div class="ish-draw-tools">
          <button class="button" data-mode="select">Select</button>
          <button class="button" data-mode="polygon">Polygon</button>
          <button class="button" data-mode="rect">Rectangle</button>
          <button class="button" data-mode="circle">Circle</button>
          <button class="button button-secondary" id="ish-finish-polygon">Finish Polygon</button>
        </div>
        <div class="ish-hotspot-panel">
          <div class="ish-hotspot-list">
            <h4>Hotspots</h4>
            <ul id="ish-hotspots"></ul>
          </div>
          <div class="ish-hotspot-editor" id="ish-hotspot-editor">
            <h4>Hotspot Details</h4>
            <label>
              Label
              <input type="text" class="ish-input" id="ish-hotspot-label" />
            </label>
            <label>
              Tooltip (HTML allowed)
              <textarea class="ish-input" id="ish-hotspot-tooltip" rows="3"></textarea>
            </label>
            <div class="ish-field-group">
              <label>Hover Fill <input type="color" id="ish-hover-fill" /></label>
              <label>Opacity <input type="number" min="0" max="1" step="0.05" id="ish-hover-opacity" /></label>
              <label>Stroke <input type="color" id="ish-hover-stroke" /></label>
            </div>
            <label>
              Action
              <select id="ish-hotspot-action">
                <option value="noop">No Action</option>
                <option value="goto_scene">Go to Scene</option>
                <option value="open_url">Open URL</option>
                <option value="open_modal">Open Modal</option>
              </select>
            </label>
            <div id="ish-action-fields"></div>
            <button class="button button-link-delete" id="ish-delete-hotspot">Delete Hotspot</button>
          </div>
        </div>
      </div>
    </div>
  `;

  app.innerHTML = appTemplate;

  const scenesList = document.getElementById('ish-scenes');
  const hotspotsList = document.getElementById('ish-hotspots');
  const sceneTitleInput = document.getElementById('ish-scene-title');
  const sceneImageButton = document.getElementById('ish-scene-image');
  const sceneImagePreview = document.getElementById('ish-scene-image-preview');
  const overlay = document.getElementById('ish-overlay');
  const canvas = document.getElementById('ish-canvas');
  const hotspotLabelInput = document.getElementById('ish-hotspot-label');
  const hotspotTooltipInput = document.getElementById('ish-hotspot-tooltip');
  const hotspotActionSelect = document.getElementById('ish-hotspot-action');
  const actionFields = document.getElementById('ish-action-fields');
  const hoverFillInput = document.getElementById('ish-hover-fill');
  const hoverOpacityInput = document.getElementById('ish-hover-opacity');
  const hoverStrokeInput = document.getElementById('ish-hover-stroke');

  document.getElementById('ish-add-scene').addEventListener('click', () => {
    const sceneTitle = window.prompt('Scene title', 'New Scene');
    if (!sceneTitle) {
      return;
    }
    const newScene = {
      id: generateId('scene'),
      title: sceneTitle,
      imageId: 0,
      imageUrl: '',
      zoom: { x: 0, y: 0, z: 1 },
      hotspots: [],
    };
    projectData.scenes.push(newScene);
    currentSceneId = newScene.id;
    currentHotspotId = null;
    render();
    persist();
  });

  document.querySelectorAll('.ish-draw-tools button[data-mode]').forEach((button) => {
    button.addEventListener('click', () => {
      drawMode = button.dataset.mode;
      polygonDraft = [];
      drawStart = null;
      updateActiveTool();
    });
  });

  document.getElementById('ish-finish-polygon').addEventListener('click', () => {
    if (polygonDraft.length >= 3) {
      addHotspot({
        type: 'polygon',
        coordinates: polygonDraft.slice(),
      });
    }
    polygonDraft = [];
    renderOverlay();
  });

  sceneTitleInput.addEventListener('input', () => {
    const scene = getCurrentScene();
    if (!scene) {
      return;
    }
    scene.title = sceneTitleInput.value;
    renderScenesList();
    persist();
  });

  sceneImageButton.addEventListener('click', () => {
    const scene = getCurrentScene();
    if (!scene) {
      return;
    }

    const frame = wp.media({
      title: 'Select Scene Image',
      button: { text: 'Use this image' },
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      scene.imageId = attachment.id;
      scene.imageUrl = attachment.url;
      renderSceneDetails();
      persist();
    });

    frame.open();
  });

  hotspotsList.addEventListener('click', (event) => {
    const item = event.target.closest('li');
    if (!item) {
      return;
    }
    currentHotspotId = item.dataset.id;
    renderHotspotEditor();
    renderOverlay();
  });

  document.getElementById('ish-delete-hotspot').addEventListener('click', () => {
    const scene = getCurrentScene();
    if (!scene || !currentHotspotId) {
      return;
    }
    scene.hotspots = scene.hotspots.filter((hotspot) => hotspot.id !== currentHotspotId);
    currentHotspotId = null;
    render();
    persist();
  });

  hotspotLabelInput.addEventListener('input', () => {
    updateCurrentHotspot((hotspot) => {
      hotspot.label = hotspotLabelInput.value;
    });
  });

  hotspotTooltipInput.addEventListener('input', () => {
    updateCurrentHotspot((hotspot) => {
      hotspot.tooltip = hotspotTooltipInput.value;
    });
  });

  hoverFillInput.addEventListener('input', () => {
    updateCurrentHotspot((hotspot) => {
      hotspot.hover_style.fill = hoverFillInput.value;
    });
  });

  hoverOpacityInput.addEventListener('input', () => {
    updateCurrentHotspot((hotspot) => {
      hotspot.hover_style.fillOpacity = parseFloat(hoverOpacityInput.value || 0);
    });
  });

  hoverStrokeInput.addEventListener('input', () => {
    updateCurrentHotspot((hotspot) => {
      hotspot.hover_style.stroke = hoverStrokeInput.value;
    });
  });

  hotspotActionSelect.addEventListener('change', () => {
    updateCurrentHotspot((hotspot) => {
      hotspot.action.type = hotspotActionSelect.value;
    });
    renderActionFields();
  });

  overlay.addEventListener('mousedown', (event) => {
    const scene = getCurrentScene();
    if (!scene || !scene.imageUrl) {
      return;
    }

    const point = getNormalizedPoint(event);
    if (!point) {
      return;
    }

    if (drawMode === 'rect' || drawMode === 'circle') {
      drawStart = point;
    }
  });

  overlay.addEventListener('mousemove', (event) => {
    if (!drawStart || (drawMode !== 'rect' && drawMode !== 'circle')) {
      return;
    }
    const point = getNormalizedPoint(event);
    if (!point) {
      return;
    }
    renderOverlay(point);
  });

  overlay.addEventListener('mouseup', (event) => {
    if (!drawStart || (drawMode !== 'rect' && drawMode !== 'circle')) {
      return;
    }
    const point = getNormalizedPoint(event);
    if (!point) {
      return;
    }

    if (drawMode === 'rect') {
      const rect = createRect(drawStart, point);
      addHotspot({ type: 'rect', coordinates: rect });
    }

    if (drawMode === 'circle') {
      const circle = createCircle(drawStart, point);
      addHotspot({ type: 'circle', coordinates: circle });
    }

    drawStart = null;
    renderOverlay();
  });

  overlay.addEventListener('click', (event) => {
    if (drawMode !== 'polygon') {
      return;
    }
    const point = getNormalizedPoint(event);
    if (!point) {
      return;
    }
    polygonDraft.push(point);
    renderOverlay();
  });

  function render() {
    renderScenesList();
    renderSceneDetails();
    renderHotspotEditor();
    renderOverlay();
    updateActiveTool();
  }

  function renderScenesList() {
    scenesList.innerHTML = '';
    projectData.scenes.forEach((scene) => {
      const li = document.createElement('li');
      li.textContent = scene.title || 'Untitled Scene';
      li.dataset.id = scene.id;
      li.draggable = true;
      if (scene.id === currentSceneId) {
        li.classList.add('active');
      }
      li.addEventListener('click', () => {
        currentSceneId = scene.id;
        currentHotspotId = null;
        render();
      });
      attachDragHandlers(li, projectData.scenes, (fromIndex, toIndex) => {
        const moved = projectData.scenes.splice(fromIndex, 1)[0];
        projectData.scenes.splice(toIndex, 0, moved);
        renderScenesList();
        persist();
      });
      scenesList.appendChild(li);
    });
  }

  function renderSceneDetails() {
    const scene = getCurrentScene();
    if (!scene) {
      sceneTitleInput.value = '';
      sceneImagePreview.removeAttribute('src');
      sceneImagePreview.classList.remove('has-image');
      return;
    }
    sceneTitleInput.value = scene.title || '';
    if (scene.imageUrl) {
      sceneImagePreview.src = scene.imageUrl;
      sceneImagePreview.classList.add('has-image');
    } else {
      sceneImagePreview.removeAttribute('src');
      sceneImagePreview.classList.remove('has-image');
    }
    renderHotspotsList();
  }

  function renderHotspotsList() {
    const scene = getCurrentScene();
    hotspotsList.innerHTML = '';
    if (!scene) {
      return;
    }
    scene.hotspots.forEach((hotspot) => {
      const li = document.createElement('li');
      li.dataset.id = hotspot.id;
      li.textContent = hotspot.label || hotspot.type;
      if (hotspot.id === currentHotspotId) {
        li.classList.add('active');
      }
      li.draggable = true;
      attachDragHandlers(li, scene.hotspots, (fromIndex, toIndex) => {
        const moved = scene.hotspots.splice(fromIndex, 1)[0];
        scene.hotspots.splice(toIndex, 0, moved);
        renderHotspotsList();
        persist();
      });
      hotspotsList.appendChild(li);
    });
  }

  function renderHotspotEditor() {
    const hotspot = getCurrentHotspot();
    const editor = document.getElementById('ish-hotspot-editor');
    if (!hotspot) {
      editor.classList.add('is-empty');
      hotspotLabelInput.value = '';
      hotspotTooltipInput.value = '';
      hoverFillInput.value = defaultHover.fill;
      hoverOpacityInput.value = defaultHover.fillOpacity;
      hoverStrokeInput.value = defaultHover.stroke;
      hotspotActionSelect.value = 'noop';
      actionFields.innerHTML = '';
      return;
    }
    editor.classList.remove('is-empty');
    hotspotLabelInput.value = hotspot.label || '';
    hotspotTooltipInput.value = hotspot.tooltip || '';
    hotspotActionSelect.value = hotspot.action.type || 'noop';
    hoverFillInput.value = hotspot.hover_style.fill || defaultHover.fill;
    hoverOpacityInput.value = hotspot.hover_style.fillOpacity ?? defaultHover.fillOpacity;
    hoverStrokeInput.value = hotspot.hover_style.stroke || defaultHover.stroke;
    renderActionFields();
  }

  function renderActionFields() {
    const hotspot = getCurrentHotspot();
    if (!hotspot) {
      actionFields.innerHTML = '';
      return;
    }
    const actionType = hotspot.action.type;
    if (actionType === 'goto_scene') {
      const options = projectData.scenes
        .map((scene) => `<option value="${scene.id}">${scene.title || 'Untitled Scene'}</option>`)
        .join('');
      actionFields.innerHTML = `
        <label>
          Target Scene
          <select id="ish-action-target">${options}</select>
        </label>
      `;
      const targetSelect = document.getElementById('ish-action-target');
      targetSelect.value = hotspot.action.target_scene_id || '';
      targetSelect.addEventListener('change', () => {
        hotspot.action.target_scene_id = targetSelect.value;
        persist();
      });
      return;
    }
    if (actionType === 'open_url') {
      actionFields.innerHTML = `
        <label>
          URL
          <input type="url" class="ish-input" id="ish-action-url" />
        </label>
        <label class="ish-checkbox">
          <input type="checkbox" id="ish-action-blank" /> Open in new tab
        </label>
      `;
      const urlInput = document.getElementById('ish-action-url');
      const blankInput = document.getElementById('ish-action-blank');
      urlInput.value = hotspot.action.url || '';
      blankInput.checked = !!hotspot.action.target_blank;
      urlInput.addEventListener('input', () => {
        hotspot.action.url = urlInput.value;
        persist();
      });
      blankInput.addEventListener('change', () => {
        hotspot.action.target_blank = blankInput.checked;
        persist();
      });
      return;
    }
    if (actionType === 'open_modal') {
      actionFields.innerHTML = `
        <label>
          Modal Content
          <textarea class="ish-input" id="ish-action-modal" rows="4"></textarea>
        </label>
      `;
      const modalInput = document.getElementById('ish-action-modal');
      modalInput.value = hotspot.action.modal_content || '';
      modalInput.addEventListener('input', () => {
        hotspot.action.modal_content = modalInput.value;
        persist();
      });
      return;
    }

    actionFields.innerHTML = '';
  }

  function renderOverlay(previewPoint) {
    const scene = getCurrentScene();
    overlay.innerHTML = '';
    if (!scene) {
      return;
    }

    scene.hotspots.forEach((hotspot) => {
      const element = createShape(hotspot);
      if (!element) {
        return;
      }
      element.dataset.id = hotspot.id;
      if (hotspot.id === currentHotspotId) {
        element.classList.add('is-selected');
      }
      overlay.appendChild(element);
    });

    if (drawMode === 'polygon' && polygonDraft.length) {
      const preview = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
      preview.setAttribute('points', polygonDraft.map((p) => `${p.x},${p.y}`).join(' '));
      preview.classList.add('ish-preview');
      overlay.appendChild(preview);
    }

    if ((drawMode === 'rect' || drawMode === 'circle') && drawStart && previewPoint) {
      let previewShape = null;
      if (drawMode === 'rect') {
        const rect = createRect(drawStart, previewPoint);
        previewShape = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        previewShape.setAttribute('x', rect.x);
        previewShape.setAttribute('y', rect.y);
        previewShape.setAttribute('width', rect.w);
        previewShape.setAttribute('height', rect.h);
      } else {
        const circle = createCircle(drawStart, previewPoint);
        previewShape = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        previewShape.setAttribute('cx', circle.cx);
        previewShape.setAttribute('cy', circle.cy);
        previewShape.setAttribute('r', circle.r);
      }
      if (previewShape) {
        previewShape.classList.add('ish-preview');
        overlay.appendChild(previewShape);
      }
    }
  }

  function addHotspot({ type, coordinates }) {
    const scene = getCurrentScene();
    if (!scene) {
      return;
    }
    const newHotspot = {
      id: generateId('hotspot'),
      type,
      coordinates,
      label: '',
      tooltip: '',
      hover_style: { ...defaultHover },
      action: {
        type: 'noop',
        target_scene_id: '',
        url: '',
        target_blank: false,
        modal_content: '',
      },
    };
    scene.hotspots.push(newHotspot);
    currentHotspotId = newHotspot.id;
    renderHotspotsList();
    renderHotspotEditor();
    renderOverlay();
    persist();
  }

  function createShape(hotspot) {
    if (!hotspot || !hotspot.coordinates) {
      return null;
    }
    if (hotspot.type === 'polygon') {
      const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
      polygon.setAttribute('points', hotspot.coordinates.map((p) => `${p.x},${p.y}`).join(' '));
      polygon.classList.add('ish-hotspot');
      return polygon;
    }
    if (hotspot.type === 'rect') {
      const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      rect.setAttribute('x', hotspot.coordinates.x);
      rect.setAttribute('y', hotspot.coordinates.y);
      rect.setAttribute('width', hotspot.coordinates.w);
      rect.setAttribute('height', hotspot.coordinates.h);
      rect.classList.add('ish-hotspot');
      return rect;
    }
    if (hotspot.type === 'circle') {
      const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      circle.setAttribute('cx', hotspot.coordinates.cx);
      circle.setAttribute('cy', hotspot.coordinates.cy);
      circle.setAttribute('r', hotspot.coordinates.r);
      circle.classList.add('ish-hotspot');
      return circle;
    }
    return null;
  }

  function getCurrentScene() {
    return projectData.scenes.find((scene) => scene.id === currentSceneId);
  }

  function getCurrentHotspot() {
    const scene = getCurrentScene();
    if (!scene) {
      return null;
    }
    return scene.hotspots.find((hotspot) => hotspot.id === currentHotspotId);
  }

  function updateCurrentHotspot(updateCallback) {
    const hotspot = getCurrentHotspot();
    if (!hotspot) {
      return;
    }
    updateCallback(hotspot);
    renderHotspotsList();
    renderOverlay();
    persist();
  }

  function updateActiveTool() {
    document.querySelectorAll('.ish-draw-tools button[data-mode]').forEach((button) => {
      button.classList.toggle('is-active', button.dataset.mode === drawMode);
    });
  }

  function getNormalizedPoint(event) {
    const rect = canvas.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width;
    const y = (event.clientY - rect.top) / rect.height;
    if (x < 0 || x > 1 || y < 0 || y > 1) {
      return null;
    }
    return { x: clamp(x), y: clamp(y) };
  }

  function createRect(start, end) {
    const x = Math.min(start.x, end.x);
    const y = Math.min(start.y, end.y);
    return {
      x,
      y,
      w: Math.abs(end.x - start.x),
      h: Math.abs(end.y - start.y),
    };
  }

  function createCircle(start, end) {
    const dx = end.x - start.x;
    const dy = end.y - start.y;
    const r = Math.sqrt(dx * dx + dy * dy);
    return {
      cx: start.x,
      cy: start.y,
      r,
    };
  }

  function clamp(value) {
    return Math.min(1, Math.max(0, value));
  }

  function generateId(prefix) {
    if (window.crypto && window.crypto.randomUUID) {
      return `${prefix}-${window.crypto.randomUUID()}`;
    }
    return `${prefix}-${Date.now()}-${Math.floor(Math.random() * 100000)}`;
  }

  function persist() {
    dataInput.value = JSON.stringify(projectData);
  }

  function attachDragHandlers(element, list, onMove) {
    element.addEventListener('dragstart', (event) => {
      event.dataTransfer.setData('text/plain', element.dataset.id);
    });
    element.addEventListener('dragover', (event) => {
      event.preventDefault();
    });
    element.addEventListener('drop', (event) => {
      event.preventDefault();
      const draggedId = event.dataTransfer.getData('text/plain');
      const fromIndex = list.findIndex((item) => item.id === draggedId);
      const toIndex = list.findIndex((item) => item.id === element.dataset.id);
      if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex) {
        return;
      }
      onMove(fromIndex, toIndex);
    });
  }

  render();
})();
