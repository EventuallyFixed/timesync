// app.js
// Javascript for the application

var includeFileBrowseDir;
var includeType;
var excludeFileBrowseDir;
var excludeType;

// OnLoad
$( document ).ready(function() {

  $("#homelink").click(function(){
    var pdata = new Object();
    pdata.fn = "profiles_list";

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
//      timeout: 500,
      success: function (data,status,xhr) {
        console.log(data);
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
      }
    });
  });


  // Update a settings value on leaving the field
  $(".autoupd").change(function(){

    // Is it a checkbox, or a radio button
    var eltType = $(this).attr('type');
    var eltVal = "";

    // At the init stage we remove autoupd, but later reinstate it
    if ($(this).hasClass('autoupd')) {
      switch ($(this).attr('type')) {
        case "checkbox":
          eltVal = 0;
          if ($(this).is(":checked")) eltVal = 1;
          break;
        default:
          eltVal = $(this).val();
      }

      var pdata = new Object();
      pdata.fn = "updateprofilesetting";
      pdata.profileid = $('#selectprofile').val();
      pdata.settingname = $(this).attr('id');
      pdata.settingvalue = eltVal;

      $.ajax('./vendor/app/php/app.php',
      {
        dataType: 'json',
        type: 'POST',
        data: pdata,
        success: function (data,status,xhr) {
          console.log(data);
        },
        error: function (jqXhr, textStatus, errorMessage) {
          console.log('Error: ' + errorMessage);
        }
      });
    } // has autoupd class
  });


  // On change of Profile ID, get and apply the new settings to screen
  $("#selectprofile").change(function(){

    var pdata = new Object();
    pdata.fn = "selectprofilesettings";
    pdata.profileid = $('#selectprofile').val();

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);

        // Temporarily remove the 'autoupd' class from the element
        var elts = $('.autoupd');
        elts.each(function(i, elt){
          $(elt).removeClass('autoupd');
        });

        if (data.items.length > 0) {
          $.each(data.items, function (i, item) {

            var elt = $('#'+item.setkey);

            switch (elt.attr('type')) {
              case "checkbox":
              case "radio":
                if (item.setval == 1) elt.attr('checked','checked');
                break;
              default:
                elt.val(item.setval);
            }
          });
        }

        // Trigger a change of those elements having dependent elements
        $('#settingsdeleteolderthan').trigger('change');
        $('#settingsdeletefreespacelessthan').trigger('change');
        $('#settingsdeleteinodeslessthan').trigger('change');
        $('#settingssmartkeepallfordays').trigger('change');
        $('#settingssmartkeeponeperdayfordays').trigger('change');
        $('#settingssmartkeeponeperdayforweeks').trigger('change');
        $('#settingssmartkeeponepermonthformonths').trigger('change');
        $('#settingssmartremove').trigger('change');
        $('#settingshost').trigger('change');

        getIncludeItems();
        getExcludeItems();

        // Reinstate the 'autoupd' class to the element
        elts.each(function(i, elt){
          $(elt).addClass('autoupd');
        });
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
      }
    });

  });


  $("#selectmode").change(function(){
    // Undisplay all of the items
    $('.modelocalencrypted, .modessh, .modesshencrypted').css('display', 'none');
    // Display the required items
    $('.'+$('#selectmode').val()).fadeIn();
  });


  $('#settingssmartremove').change(function(){
    // Enable or diable the dependent elements
    var ischecked = 0;
    if ($(this).is(":checked")) ischecked = 1;

    $('#smartremovediv').find('input').each(function(i, elt) {
      if (ischecked == 0) $(elt).attr('disabled','disabled');
      else $(elt).removeAttr('disabled');
    });
  });

  $('#settingsdeleteolderthan').change(function(){
    // Enable or diable the dependent elements
    if ($(this).is(":checked")) {
      $('#settingsdeleteolderthanage').removeAttr('disabled');
      $('#settingsdeletebackupolderthanperiod').removeAttr('disabled');
      $('#settingsdeletebackupolderthanperiod').trigger('change'); // store the value
    }
    else {
      $('#settingsdeleteolderthanage').attr('disabled','disabled');
      $('#settingsdeletebackupolderthanperiod').attr('disabled','disabled');
    }
  });

   $('#settingsdeletefreespacelessthan').change(function(){
    // Enable or diable the dependent elements
    if ($(this).is(":checked")) {
      $('#settingsdeletefreespacelessthanvalue').removeAttr('disabled');
      $('#settingsdeletefreespacelessthanunit').removeAttr('disabled');
      $('#settingsdeletefreespacelessthanunit').trigger('change'); // store the value
    }
    else {
      $('#settingsdeletefreespacelessthanvalue').attr('disabled','disabled');
      $('#settingsdeletefreespacelessthanunit').attr('disabled','disabled');
    }
  });

  $('#settingsdeleteinodeslessthan').change(function(){
    // Enable or diable the dependent elements
    if ($(this).is(":checked")) {
      $('#settingsdeleteinodeslessthanvalue').removeAttr('disabled');
    }
    else {
      $('#settingsdeleteinodeslessthanvalue').attr('disabled','disabled');;
    }
  });


  $('#excludeadddefault').click(function() {
    var pdata = new Object();
    pdata.fn = "adddefaultexcludes";
    pdata.profileid = $('#selectprofile').val();

    // Provide feedback to the user
    var spinelt = createSpinner('excludefilescontainer','');
    spinelt.css('top','5px');
    spinelt.css('left','250px');

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);

        // Refresh the exclude list
        getExcludeItems()
        spinelt.remove();
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
        spinelt.remove();
      }
    });
  });


  // Click Exclude Remove button
  $('#excluderemove').click(function(){
    var idtoremove = $("#thisexcludesetid").val();
    $("body").off("click", idtoremove); // remove the click event

    var pdata = new Object();
    pdata.fn = "deleteprofilesetting";
    pdata.settingid = $('#'+idtoremove).attr('setid');

    // Provide feedback to the user
    var spinelt = createSpinner('excludefilescontainer','');
    spinelt.css('top','5px');
    spinelt.css('left','250px');

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);

        if (data.result == "ok") {
          $("body").off("click", "#"+idtoremove); // remove the click event
          // Remove the element childnodes & element
          $('#'+idtoremove).empty();
          $('#'+idtoremove).remove();
        }
        spinelt.remove();
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
        spinelt.remove();
      }
    });
  });


  // Click Include Remove button
  $('#includeremove').click(function(){
    var idtoremove = $("#thisincludesetid").val();
    $("body").off("click", idtoremove); // remove the click event

    var pdata = new Object();
    pdata.fn = "deleteprofilesetting";
    pdata.settingid = $('#'+idtoremove).attr('setid');

    // Provide feedback to the user
    var spinelt = createSpinner('includefilescontainer','');
    spinelt.css('top','5px');
    spinelt.css('left','190px');

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);

        if (data.result == "ok") {
          $("body").off("click", "#"+idtoremove); // remove the click event
          // Remove the element childnodes & element
          $('#'+idtoremove).empty();
          $('#'+idtoremove).remove();
        }
        spinelt.remove();
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
        spinelt.remove();
      }
    });
  });


  // Click of Include Add Folder
  $("#includeaddfolder").click(function(){
    // Remove the includefilescontainer
    $("#includefilescontainer").css("display","none");
    $("#includefilesbuttons").css("display","none");
    // Ensure the body of the file browse is clear
    $("#includefilebrowsebody").empty();
    // Fade in the includeaddfilebrowse
    $("#includeaddfilebrowse").fadeIn();

    includeFileBrowseDir = "/shares";
    includeType = "d";
    getDirectoryContents("include", includeType, includeFileBrowseDir, "");
  });


  // Click of Include Add File
  $('#includeaddfile').click(function() {

    // Remove the includefilescontainer
    $("#includefilescontainer").css("display","none");
    $("#includefilesbuttons").css("display","none");
    // Ensure the body of the file browse is clear
    $("#includefilebrowsebody").empty();
    // Fade in the includeaddfilebrowse
    $("#includeaddfilebrowse").fadeIn();

    includeFileBrowseDir = "/shares";
    includeType = "-";
    getDirectoryContents("include", includeType, includeFileBrowseDir, "");
  });


  // Cancel out of the Include Files browser
  $("#includeaddcancel").click(function(){
    $("#includeaddfilebrowse").css("display","none");
    $("#includefilescontainer").fadeIn();
    $("#includefilesbuttons").fadeIn();
  });

  // Click of Select in Include Files Browse
  $("#includeaddselect").click(function(){
    var selFile = includeFileBrowseDir + "/" + $("#includeaddfilebrowse").find(".inclexclsel").attr("filename");
    var selType = $("#includeaddfilebrowse").find(".inclexclsel").attr("filetype");
    $("#includeaddfilebrowse").css("display","none");
    $("#includefilescontainer").fadeIn();
    $("#includefilesbuttons").fadeIn();

    // Now add the path to the criteria
    var pdata = new Object();
    pdata.fn = "insertincludefilefolder";
    pdata.profileid = $('#selectprofile').val();
    pdata.filepath = selFile;
    pdata.filetype = selType;

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);

        // If OK, refresh the include list
        getIncludeItems();
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
        spinelt.remove();
      }
    });

  });


  // Cancel out of the Exclude Files browser
  $("#excludeaddcancel").click(function(){
    $("#excludeaddfilebrowse").css("display","none");
    $("#excludefilescontainer").fadeIn();
    $("#excludefilesbuttons").fadeIn();
  });


  // Click of Exclude Add File
  $('#excludeaddfile').click(function() {
    // Remove the excludefilescontainer
    $("#excludefilescontainer").css("display","none");
    $("#excludefilesbuttons").css("display","none");
    // Ensure the body of the file browse is clear
    $("#excludefilebrowsebody").empty();
    // Fade in the excludeaddfilebrowse
    $("#excludeaddfilebrowse").fadeIn();

    excludeFileBrowseDir = "/shares";
    excludeType = "-";
    getDirectoryContents("exclude", excludeType, excludeFileBrowseDir, "");
  });

  // Click of Exclude Add Folder
  $("#excludeaddfolder").click(function(){
    // Remove the excludefilescontainer
    $("#excludefilescontainer").css("display","none");
    $("#excludefilesbuttons").css("display","none");
    // Ensure the body of the file browse is clear
    $("#excludefilebrowsebody").empty();
    // Fade in the excludeaddfilebrowse
    $("#excludeaddfilebrowse").fadeIn();

    excludeFileBrowseDir = "/shares";
    excludeType = "d";
    getDirectoryContents("exclude", excludeType, excludeFileBrowseDir, "");
  });

  // Click of select in the exclude file browse
  $("#excludeaddselect").click(function(){
    var selFile = excludeFileBrowseDir + "/" + $("#excludeaddfilebrowse").find(".inclexclsel").attr("filename");
    var selType = $("#excludeaddfilebrowse").find(".inclexclsel").attr("filetype");
    $("#excludeaddfilebrowse").css("display","none");
    $("#excludefilescontainer").fadeIn();
    $("#excludefilesbuttons").fadeIn();

    // Now add the path to the criteria
    var pdata = new Object();
    pdata.fn = "insertexcludefilefolder";
    pdata.profileid = $('#selectprofile').val();
    pdata.filepath = selFile;
    pdata.filetype = selType;

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);

        // If OK, refresh the exclude list
        getExcludeItems();
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
        spinelt.remove();
      }
    });
  });

  // Cancel out of the Exclude Misc Item browse
  $("#excludeaddmiscitemcancel").click(function(){
    $("#excludeaddmiscitembrowse").css("display","none");
    $("#excludefilescontainer").fadeIn();
    $("#excludefilesbuttons").fadeIn();
  });

  // Click of Exclude Add Misc Item
  $('#excludeadd').click(function() {
    // Remove the excludefilescontainer
    $("#excludefilescontainer").css("display","none");
    $("#excludefilesbuttons").css("display","none");
    // Ensure the input value is cleared
    $("#excludeaddmisciteminput").val("");
    // Fade in the excludeaddmiscitembrowse
    $("#excludeaddmiscitembrowse").fadeIn();
  });

  // Click of select in the exclude misc item browse
  $("#excludeaddmiscitemselect").click(function(){
    var selFile = $("#excludeaddmisciteminput").val();
    var selType = "miscexclude";
    // Show the Exclude Items Browse
    $("#excludeaddmiscitembrowse").css("display","none");
    $("#excludefilescontainer").fadeIn();
    $("#excludefilesbuttons").fadeIn();

    // Now add the item to the criteria
    var pdata = new Object();
    pdata.fn = "insertexcludefilefolder";
    pdata.profileid = $('#selectprofile').val();
    pdata.filepath = selFile;
    pdata.filetype = selType;

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);

        // If OK, refresh the exclude list
        getExcludeItems();
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
        spinelt.remove();
      }
    });
  });


// ============================================================================================================================================

  $('#settingshost').change(function(){
    SetSettingsFullSnapshotPath();
  });
  $('#settingsuser').change(function(){
    SetSettingsFullSnapshotPath();
  });
  $('#settingsprofile').change(function(){
    SetSettingsFullSnapshotPath();
  });

  Init();

}); // Page Ready

// Get a description for the types
function getIncExcTypeDesc(type) {
  var desc = "";
  switch (type) {
    case "-":
      desc = "file";
      break;
    case "d":
      desc = "folder";
      break;
    case "l":
      desc = "link";
      break;
    default:
      desc = "unknown";
  }
  return desc;
}

function getDirectoryContents(actiontype, type, dir, sel){

  var pdata = new Object();
  pdata.fn = "getdirectorycontents";
  pdata.type = type;
  pdata.dir = dir;
  pdata.sel = sel;

  // Get a description of what is being searched for
  var Bdesc = "Select "+getIncExcTypeDesc(type)+" to "+actiontype;
  $("#"+actiontype+"addfilebrowse").find("nav").text(Bdesc);

  // Ensure the body of the file browse is clear
  $("#"+actiontype+"filebrowsebody").empty();

  // Provide feedback to the user
  var spinelt = createSpinner(actiontype+'filebrowsebody','');

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);

      spinelt.remove();

      // Draw the divs for the contents
      $("#"+actiontype+"filebrowsebody").empty();
      if (data.result == "ok") {
        $.each(data.items, function (i, item) {
          createFileLine(actiontype, type, item, $("#"+actiontype+"filebrowsebody"));
        });
      }
      if (actiontype == "include") includeFileBrowseDir = data.newdir;
      else excludeFileBrowseDir = data.newdir;

      $("#"+actiontype+"addfilelocation").text(data.newdir);
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);

      spinelt.remove();
    }
  });
}


// Draw the divs for the contents
function createFileLine(actiontype, type, item, celt) {
  var iconname = "";
  switch (item.filetype.toLowerCase()) {
    case "d":
      iconName = "mdi-folder-outline";
      break;
    case "f":
      iconName = "mdi-file-outline";
      break;
    case "l":
      iconName = "mdi-file-link-outline";
      break;
    default:
      iconName = "mdi-file-question-outline";
      break;
  }
  var rowelt = $("<div/>").addClass("row snaprow").attr("id","file_"+item.id).attr("filetype",item.filetype.toLowerCase()).attr("filename",item.filename);
  var imgelt = $("<span/>").addClass("iconify").attr("id","icon_"+item.id).attr("data-icon",iconName).appendTo(rowelt);
  var namelt = $("<span/>").addClass("").attr("id","name_"+item.id).text(item.filename).appendTo(rowelt);
  rowelt.appendTo(celt);

  // Add trigger
  rowelt.click(function() {
    // Get the id of the currently selected item using the class name
    var selid = $(this).attr("id");
    // Get all items in the area with the 'inclexclsel' class, and remove that class
    var elts = celt.find(".inclexclsel").removeClass("inclexclsel");
    // Set the value of the hidden selector, if it is not the same setid
    $(this).addClass("inclexclsel");

    if (type == $(this).attr("filetype")) {
      $("#"+actiontype+"addselect").removeAttr("disabled");
    }
    else {
      $("#"+actiontype+"addselect").attr("disabled","disabled");
    }
  });

  // Add trigger
  rowelt.dblclick(function() {
    // You can't drill down into a file
    if ($(this).attr("filetype") != "-") {
      // Callback to get the directory listing
      if (actiontype == "include") getDirectoryContents(actiontype, type, includeFileBrowseDir, $(this).text());
      else getDirectoryContents(actiontype, type, excludeFileBrowseDir, $(this).text());
    }
  });

}

function SetSettingsFullSnapshotPath(){
  $('#settingsfullsnapshotpath').val( $('#settingssaveto').val()+'/timesync/'+$('#settingshost').val()+'/'+$('#settingsuser').val()+'/'+$('#settingsprofile').val() );
}


function createSpinner(containerid, size) {
   var spinelt = $('<div/>').addClass("loader"+size).css('position','absolute').appendTo('#'+containerid);
   return spinelt;
}


function getIncludeItems() {

  var pdata = new Object();
  pdata.fn = "getincludepatterns";
  pdata.profileid = $('#selectprofile').val();

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);

      if (data.items.length > 0) {
        // Remove current items here
        $('#includefilescontainer').find('.inclitem').remove();

        // Populate new items
        $.each(data.items, function (i, item) {

          // include items in the frontend
          insertIncludeItem(item);

          // Add trigger
          var eltid = 'inclrow_'+item.setid;
          $('#'+eltid).click(function() {
            // Get the id of the currently selected item using the class name
            var selid = $("#includefilescontainer").find(".inclexclsel").attr("id");
            // Get all items in the area with the 'inclexclsel' class, and remove that class
            var elts = $("#includefilescontainer").find(".inclexclsel").removeClass("inclexclsel");
            // Blank the hidden input
            $("#thisincludesetid").val('');
            // Disable the remove button
            $('#includeremove').attr('disabled','disabled');
            // Set the value of the hidden selector, if it is not the same setid
            if (selid != $(this).attr("id")) {
              $("#thisincludesetid").val($(this).attr("id"));
              $(this).addClass("inclexclsel");
              $('#includeremove').removeAttr('disabled');
            }
          });

        });
      }
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
    }
  });
}


function insertIncludeItem(item) {

  var cdiv = $('<div/>').attr('id','inclrow_'+item.setid).attr('setid',item.setid).addClass('row snaprow inclitem');
  var fidiv = $('<div/>').attr('id','inclcell1_'+item.setid).addClass('snapcolumn col-1').text('O').appendTo(cdiv);
  var fndiv = $('<div/>').attr('id','inclcell2_'+item.setid).addClass('snapcolumn col-11').text(item.setval).appendTo(cdiv);
  cdiv.appendTo($('#includefilescontainer'));
}


function getExcludeItems() {

  var pdata = new Object();
  pdata.fn = "getexcludepatterns";
  pdata.profileid = $('#selectprofile').val();

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);

      if (data.items.length > 0) {
        // Remove current items here
        $('#excludefilescontainer').find('.exclitem').remove();

        // Populate new items
        $.each(data.items, function (i, item) {
          // include items in the frontend
          insertExcludeItem(item);

          // Add trigger
          var eltid = 'exclrow_'+item.setid;
          $('#'+eltid).click(function() {
            // Get the id of the currently selected item using the class name
            var selid = $("#excludefilescontainer").find(".inclexclsel").attr("id");
            // Get all items in the area with the 'inclexclsel' class, and remove that class
            var elts = $("#excludefilescontainer").find(".inclexclsel").removeClass("inclexclsel");
            // Blank the hidden input
            $("#thisexcludesetid").val('');
            // Disable the remove button
            $('#excluderemove').attr('disabled','disabled');
            // Set the value of the hidden selector, if it is not the same setid
            if (selid != $(this).attr("id")) {
              $("#thisexcludesetid").val($(this).attr("id"));
              console.log("Set selected exclude value to "+$(this).attr("id"));
              $(this).addClass("inclexclsel");
              $('#excluderemove').removeAttr('disabled');
            }
          });
        });

      }
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
    }
  });
}


function insertExcludeItem(item) {

  var cdiv = $('<div/>').attr('id','exclrow_'+item.setid).attr('setid',item.setid).addClass('row snaprow exclitem');
  var fidiv = $('<div/>').attr('id','exclcell1_'+item.setid).addClass('snapcolumn col-1').text('O').appendTo(cdiv);
  var fndiv = $('<div/>').attr('id','exclcell2_'+item.setid).addClass('snapcolumn col-11').text(item.setval).appendTo(cdiv);
  cdiv.appendTo($('#excludefilescontainer'));
}


function Init() {

  var pdata = new Object();
  pdata.fn = "init";

  // Provide feedback to the user
  var spinelt = createSpinner('snapshottoolbar','large');
  spinelt.css('top','15px');
  spinelt.css('left','350px');

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);

      // Get the Profiles select built
      BuildProfilesSelect();

      // SJT temporary convenience action for debugging
	    $("#settingsmenu").trigger("click");
      spinelt.remove();
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
      spinelt.remove();
    }
  });

}


function BuildProfilesSelect() {

  // Get the Profiles List for the DDL
  var pdata = new Object();
  pdata.fn = "selectprofileslist";

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);

      // Remove all options from the select
      $('#selectprofile').removeClass('autoupd');
      $('#selectprofile').find('option').remove();

      // Add saved items
      if (data.items.length > 0) {
        $.each(data.items, function (i, item) {
          if (item.selected == 'selected') {
            $('#selectprofile').append($('<option>', {
              value: item.id,
              text : item.profilename,
              selected: item.selected
            }));
          } else {
            $('#selectprofile').append($('<option>', {
              value: item.id,
              text : item.profilename
            }));
          }
        });
        // Trigger a change to initialise the settings
        $("#selectprofile").trigger('change');
        $('#selectprofile').addClass('autoupd');
      }
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
    }
  }); 	

}


// Side Menus
$("#snapshotsmenu").click(function(){
	$('.contentsection').css('display','none');
	$('#snapshotsection').fadeIn();
});
$("#settingsmenu").click(function(){
	$('.contentsection').css('display','none');
	$('#settingssection').fadeIn();
});
$("#logsmenu").click(function(){
	$('.contentsection').css('display','none');
	$('#logssection').fadeIn();
});
$("#aboutmenu").click(function(){
	$('.contentsection').css('display','none');
	$('#aboutsection').fadeIn();
});


// Snapshot Toolbar
// Take Snapshot
$("#snapshotnewbtn").click(function(){
	alert('Take Snapshot');
});
// Refresh Snapshot
$("#snapshotrefreshbtn").click(function(){
	alert('Refresh Snapshot');
});
// Snapshot Name
$("#snapshotnamebtn").click(function(){
	alert('Snapshot Name');
});
// Remove Snapshot
$("#snapshotremovebtn").click(function(){
	alert('Remove Snapshot');
});
// View Snapshot Log
$("#snapshotlogbtn").click(function(){
	alert('View Snapshot Log');
});
// View Last Log
$("#snapshotlastlogbtnbtn").click(function(){
	alert('View Last Log');
});
// Settings
$("#snapshotsettingsbtn").click(function(){
	$("#settingsmenu").trigger("click");
});
// Help
$("#snapshothelpbtn").click(function(){
	alert('Help');
});



// Files Toolbar
// Up
$("#fileupbtn").click(function(){
	alert('Directory Up');
});
// Toggle hidden files
$("#filetogglehiddenbtn").click(function(){
	alert('Toggle hidden files');
});
// Restore
$("#filerestorebtn").click(function(){
	alert('Restore');
});
// Restore to...
$("#filerestoretobtn").click(function(){
	alert('Restore to...');
});
// Restore '/path'
$("#filerestorepathbtn").click(function(){
	alert('Restore /path');
});
// Restore '/path' to...
$("#filerestorepathtobtn").click(function(){
	alert('Restore /path to...');
});
// Snapshots
$("#filesnapshotsbtn").click(function(){
	alert('Snapshots');
});

// Snapshot Now permanent link
$("#snapnow").click(function(){
	alert('Now Snapshot Link');
});




