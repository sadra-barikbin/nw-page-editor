// @todo Preserve window size and position when reopening app
// @todo Displace new windows so that they do not appear on top of the first
// @todo When undo/redo returns to saved state, disable save button
// @todo Option to open image, creating its corresponding XML
// @todo Save As

$(window).on('load', function () {

  var
  win = nw.Window.get(),
  pageCanvas = window.pageCanvas;

  /// Additional pageCanvas configuration ///
  pageCanvas.setConfig(
    { onUnload: function () { $('#saveFile').prop( 'disabled', true ); },
      onFirstChange: function () { $('#saveFile').prop( 'disabled', false ); $('title').text($('title').text()+' *'); },
      tiffLoader: function ( tiff ) {
          return 'data:image/jpeg;base64,'+require('child_process').execSync( 'convert '+tiff+' jpeg:- | base64' );
        }
    } );

  /// Keyboard bindings ///
  Mousetrap.bind( process.platform === "darwin" ? 'mod+option+i' : 'ctrl+shift+i', function () { win.showDevTools(); return false; } );
  Mousetrap.bind( 'mod+o', function () { $('#openFile').click(); return false; } );
  Mousetrap.bind( 'mod+s', function () { saveFile(); return false; } );
  Mousetrap.bind( 'mod+q', function () { saveSafeClose(); return false; } );
  Mousetrap.bind( 'mod+n', newWindow );
  Mousetrap.bind( 'pagedown', function () { $('#nextPage').click(); return false; } );
  Mousetrap.bind( 'pageup', function () { $('#prevPage').click(); return false; } );
  Mousetrap.bind( 'mod+shift+r', function () {
    console.log('file reload');
      if ( typeof pageCanvas !== 'undefined' && pageCanvas.hasChanged() )
        if ( confirm('WARNING: Modifications will be lost on reload! Select Cancel to abort reload.') )
          loadFile();
      return false;
    } );
  Mousetrap.bind( 'mod+option+r', function () { win.reloadIgnoringCache(); } );

  /// Multiple windows support ///
  if ( typeof global.pageNum === 'undefined' )
    global.pageNum = parseInt(window.location.hash.substr(1));
  function newWindow() {
    var b = win.appWindow.getBounds();
    global.pageNum++;
    nw.Window.open('../html/index.html#'+global.pageNum,{"width":b.width,"height":b.height});
    return false;
  }

  /// Confirm that changes will be saved on exit ///
  function saveSafeClose() {
    if ( typeof pageCanvas !== 'undefined' && pageCanvas.hasChanged() )
      if ( autosave ||
           confirm('WARNING: Modifications will be saved on exit! Select Cancel to discard them.') )
        $('#saveFile').click();
    win.close(true);
  }
  win.on( 'close', saveSafeClose ); // @todo Bug: not working when clicking on the window top bar x button
  $('#quit').click( saveSafeClose );

  /// Automatic save ///
  var autosave = false;
  $('#autoSave input').change( function () {
      autosave =
        $('#autoSave input').prop('checked') ? true : false ;
    } );
  setInterval( function () { 
      if ( autosave && pageCanvas.hasChanged() ) {
        console.log('automatic saving ...');
        $('#saveFile').click();
      }
    }, 15000 );

  /// Setup page number navigation ///
  $('#pageNum').keyup( function ( event ) { if ( event.keyCode == 13 ) changePage(0); } );
  $('#prevPage').click( function () { changePage(-1); } );
  $('#nextPage').click( function () { changePage(1); } );
  var prevNum = 0;
  function changePage( offset ) {
    var fileNum = parseInt($('#pageNum').val()) + offset;
    if ( isNaN(fileNum) || fileNum < 1 || fileNum > fileList.length )
      fileNum = prevNum === 0 ? 1 : prevNum;
    $('#pageNum').val(fileNum);
    if ( fileNum === prevNum )
      return;
    if ( pageCanvas.hasChanged() )
      if ( confirm('WARNING: Modifications will be saved on page change! Select Cancel to discard them.') )
        $('#saveFile').click();
    loadFile();
  }

  var
  osBar = ( process.platform.substr(0,3) === 'win' ? '\\' : '/' ),
  fileList,
  loadedFile = null,
  prevFileContents = null;

  /// Function that initializes the list of all *.xml provided files or all found in base directory ///
  function loadFileList( file ) {
    if ( ! file )
      return;

    var
    fileNum = 1,
    filelist = [],
    basedir,
    files,
    fs = require('fs');

    if ( Object.prototype.toString.call(file) === '[object Array]' ) {
      basedir = '';
      files = file;
      file = '';
    }
    else {
      var fstat = fs.statSync(file);
      if ( fstat.isDirectory() ) {
        basedir = file;
        file = '';
      }
      else {
        basedir = file.replace(/[/\\][^/\\]+$/,'');
        file = file.replace(/.*[/\\]/,'');
      }
      files = fs.readdirSync(basedir);
    }

    filelist = [];
    for ( var n=0; n<files.length; n++ )
      if ( files[n].substr(-4).toLowerCase() === '.xml' ) {
        filelist.push( ( basedir ? basedir+osBar : '' ) + files[n] );
        if ( file === files[n] )
          fileNum = filelist.length;
      }

    if ( filelist.length === 0 )
      pageCanvas.throwError( 'Expected at least one Page .xml file to load' );

    fileList = filelist;
    prevNum = 0;
    $('#pageNum').val(fileNum);
    $('#totPages').text(fileList.length);
    $('#prevPage, #pageNum, #nextPage').prop( 'disabled', fileList.length > 1 ? false : true );
    loadFile();
  }

  /// Function for loading the selected file into the page canvas ///
  function loadFile() {
    var fileNum = parseInt($('#pageNum').val());
    if ( isNaN(fileNum) || fileNum <= 0 || fileNum > fileList.length )
      return;

    var
    filepath = fileList[fileNum-1],
    newtitle = nw.App.manifest.window.title + ' - ' + filepath.replace( new RegExp('^'+process.env.HOME+'/'), '~/' );

    require('fs').readFile( filepath, 'utf8', function ( err, data ) {
        if ( err )
          return pageCanvas.cfg.handleError( err );
        prevFileContents = data;
        loadedFile = filepath;
        prevNum = fileNum;
        pageCanvas.loadXmlPage( data, 'file://'+filepath.replace(/[/\\][^/\\]+$/,'') );
        $('title').text(newtitle);
        $('#pageFile').text(filepath.replace(/^.+[/\\]/,'').replace(/\.xml$/,''));
        //pageCanvas.fitPage();
        //$('#pointsMode input').click();
      } );
  }

  /// Function to handle open file dialog ///
  function chooseFile( name, callback ) {
    var chooser = $(name);
    chooser.unbind('change');
    chooser.change( function ( event ) { callback($(this).val()); } );
    chooser.trigger('click');
  }

  /// Button to open file ///
  $('#openFile').click( function () {
      chooseFile( "#openFileDialog", function(filename) {
          loadFileList(filename);
        } );
    } );

  /// Open file if provided as argument ///
  // @todo Allow that an arg be a file list
  if ( nw.App.argv.length > 0 && window.location.hash === '#1' ) {
    loadFileList( nw.App.argv.length == 1 ? nw.App.argv[0] : nw.App.argv );
    window.setTimeout( function () { pageCanvas.fitPage(); }, 300 );
  }

  if ( typeof global.argv !== 'undefined' ) {
    loadFileList( global.argv.length == 1 ? global.argv[0] : global.argv );
    delete global.argv;
    window.setTimeout( function () { pageCanvas.fitPage(); }, 300 );
  }

  nw.App.on( 'open', function ( argv ) {
console.log('argv: '+argv);
      global.argv = argv.replace(/.*nw-page-editor /,'').split(' ');
      newWindow();
    } );

  /// Button to save file ///
  $('#saveFile').click( saveFile );
  function saveFile() {
    var fs = require('fs');

    if ( prevFileContents )
      fs.writeFile( loadedFile+'~', prevFileContents, function ( err ) {
          if ( err )
            pageCanvas.cfg.handleError( err );
          prevFileContents = null;
        } );

    var pageXml = pageCanvas.getXmlPage();
    fs.writeFile( loadedFile, pageXml, function ( err ) {
        if ( err )
          pageCanvas.cfg.handleError( err );
      } );

    $('#saveFile').prop( 'disabled', true );
    $('title').text($('title').text().replace(/ \*$/,''));
    pageCanvas.setUnchanged();
  }

} );