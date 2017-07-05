var viewer = OpenSeadragon({
    id: "seadragon-viewer",
    prefixUrl: "//openseadragon.github.io/openseadragon/images/",
    tileSources: drupalSettings.herbarium_specimen.dzi_filepath
});
