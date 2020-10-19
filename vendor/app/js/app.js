// app.js
// Javascript for the application

var includeFileBrowseDir;
var includeType;
var excludeFileBrowseDir;
var excludeType;
var saveToFileBrowseDir;
var saveToType;
var showHidden = 0;

// OnLoad
$(document).ready(function() {

  $("#homelink").click(function(){
    var pdata = new Object();
    pdata.fn = "profiles_list";

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
  });


  // Update a settings value on leaving the field
  $(".autoupd").change(function(){

    // Is it a checkbox, or a radio button
    var eltType = $(this).attr('type');
    var eltVal = "";
    var eltId = $(this).attr('id');

    // At the init stage we remove autoupd, but later reinstate it
    if ($(this).hasClass("autoupd")) {
      switch ($(this).attr("type")) {
        case "checkbox":
          eltVal = 0;
          if ($(this).is(":checked")) eltVal = 1;
          break;
        default:
          eltVal = $(this).val();
      }

      var pdata = new Object();
      pdata.fn = "updateprofilesetting";
      pdata.profileid = $("#selectprofile").val();
      pdata.settingname = eltId;
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

    // Enable or disable delete
    if ($(this).find('option:selected').attr("candel") == 1) { $("#delprofilebtn").removeAttr("disabled"); }
    else { $("#delprofilebtn").attr("disabled","disabled"); }

    var pdata = new Object();
    pdata.fn = "selectfullprofile";
    pdata.profileid = $("#selectprofile").val();
    pdata.dir       = $("#filedirinput").val();

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);

        // Temporarily remove the 'autoupd' class from the element
        var autoupd_elts = removeTriggerClass("autoupd");
        var scheduleupd_elts = removeTriggerClass("scheduleupd");

        // Refresh the side menu
        BuildSideMenu(data.snaplist);
        
        // Refresh the files list, to show the files of the directory bar
        var actiontype = "show";
        if (data.showfiles.length > 0) {
          $.each(data.showfiles, function (i, item) {
            createFileLine(i, actiontype, "-", item, $("#"+actiontype+"filebrowsebody"));
          });
        }

        // Reset all fields of the Settings
        resetSettings();

        // Set main settings elements
        if (data.profilesettings.length > 0) {
          $.each(data.profilesettings, function (i, item) {

            var elt = $("#"+item.setkey);

            switch (elt.attr('type')) {
              case "checkbox":
              case "radio":
                if (item.setval == 1) elt.prop("checked", true);
                break;
              default:
                elt.val(item.setval);
            }
          });
        }


        // Include Files/Folders
        // Backup Paths
        // Remove current items
        if (data.profileinclude.length > 0) {
          insertIncludeItems(data.profileinclude);
        }

        // Exclude Files/Folders
        // Remove current items
        if (data.profileexclude.length > 0) {
          insertExcludeItems(data.profileexclude);
        }

        // Trigger a change of those elements having dependent elements
        $("#settingsdeleteolderthan").trigger("change");
        $("#settingsdeletefreespacelessthan").trigger("change");
        $("#settingsdeleteinodeslessthan").trigger("change");
        $("#settingssmartkeepallfordays").trigger("change");
        $("#settingssmartkeeponeperdayfordays").trigger("change");
        $("#settingssmartkeeponeperdayforweeks").trigger("change");
        $("#settingssmartkeeponepermonthformonths").trigger("change");
        $("#settingssmartremove").trigger("change");
        $("#settingshost").trigger("change");
        showScheduleElements()

        // Re-add the trigger classes
        addTriggerClass(scheduleupd_elts, "scheduleupd");
        addTriggerClass(autoupd_elts, "autoupd");
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
    $("."+$("#selectmode").val()).fadeIn();
  });


  $("#settingssmartremove").change(function(){
    // Enable or diable the dependent elements
    var ischecked = 0;
    if ($(this).is(":checked")) ischecked = 1;

    $("#smartremovediv").find("input").each(function(i, elt) {
      if (ischecked == 0) $(elt).attr("disabled","disabled");
      else $(elt).removeAttr("disabled");
    });
  });


  $("#settingsdeleteolderthan").change(function(){
    // Enable or diable the dependent elements
    if ($(this).is(":checked")) {
      $('#settingsdeleteolderthanage').removeAttr("disabled");
      $('#settingsdeletebackupolderthanperiod').removeAttr("disabled");
      $('#settingsdeletebackupolderthanperiod').trigger("change"); // store the value
    }
    else {
      $('#settingsdeleteolderthanage').attr("disabled","disabled");
      $('#settingsdeletebackupolderthanperiod').attr("disabled","disabled");
    }
  });


  $('#settingsdeletefreespacelessthan').change(function(){
    // Enable or diable the dependent elements
    if ($(this).is(":checked")) {
      $("#settingsdeletefreespacelessthanvalue").removeAttr("disabled");
      $("#settingsdeletefreespacelessthanunit").removeAttr("disabled");
      $("#settingsdeletefreespacelessthanunit").trigger("change"); // store the value
    }
    else {
      $("#settingsdeletefreespacelessthanvalue").attr("disabled","disabled");
      $("#settingsdeletefreespacelessthanunit").attr("disabled","disabled");
    }
  });


  $("#settingsdeleteinodeslessthan").change(function(){
    // Enable or diable the dependent elements
    if ($(this).is(":checked")) {
      $("#settingsdeleteinodeslessthanvalue").removeAttr("disabled");
    }
    else {
      $("#settingsdeleteinodeslessthanvalue").attr("disabled","disabled");
    }
  });


  $("#excludeadddefault").click(function() {
    var pdata = new Object();
    pdata.fn = "adddefaultexcludes";
    pdata.profileid = $("#selectprofile").val();

    // Provide feedback to the user
    var spinelt = createSpinner("excludefilescontainer","");
    spinelt.css("top","5px");
    spinelt.css("left","250px");

    $.ajax("./vendor/app/php/app.php",
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
    pdata.fn = "deleteprofileinclexcl";
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
        $('#excluderemove').attr("disabled","disabled");
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
    pdata.fn = "deleteprofileinclexcl";
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
          
          // Remove from snapshots area, as required
          $("#snapdirsfilescontainer").find("div[setid='"+pdata.settingid+"']").remove();
        }
        $('#includeremove').attr("disabled","disabled");
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

    // Provide feedback to the user
    var spinelt = createSpinner('includefilescontainer','');
    spinelt.css('top','5px');
    spinelt.css('left','190px');

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
        spinelt.remove();

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

     // Provide feedback to the user
    var spinelt = createSpinner('excludefilescontainer','');
    spinelt.css('top','5px');
    spinelt.css('left','250px');

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
        spinelt.remove();

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

    // Provide feedback to the user
    var spinelt = createSpinner('excludefilescontainer','');
    spinelt.css('top','5px');
    spinelt.css('left','250px');

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
        spinelt.remove();

        // If OK, refresh the exclude list
        getExcludeItems();
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
        spinelt.remove();
      }
    });
  });

  $('#settingshost').change(function(){
    SetSettingsFullSnapshotPath();
  });
  $('#settingsuser').change(function(){
    SetSettingsFullSnapshotPath();
  });
  $('#settingsprofile').change(function(){
    SetSettingsFullSnapshotPath();
  });


  // Click of New Profile
  $("#newprofilebtn").click(function(){
    $("#selectprofilediv").css("display", "none");
    $("#addprofileinp").val("");
    $("#addprofilediv").fadeIn();
  });


  // Click of Add Profile
  $("#addprofilebtn").click(function(){
    // Add items to the criteria
    var pdata = new Object();
    pdata.fn = "addprofile";
    pdata.profilename = $('#addprofileinp').val();

    // Provide feedback to the user
    var spinelt = createSpinner('addprofilediv','');
    spinelt.css('top','10px');
    spinelt.css('left','130px');

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);
        spinelt.remove();

        // If OK, refresh the profiles list & select the new one
        if (data.result == "ok") {
          // Refresh the Profiles Select
          $("#addprofilediv").css("display", "none");
          $("addprofileinp").val();
          $("#selectprofilediv").fadeIn();
          BuildProfilesSelect(data.id);
        }
        else {
        }
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
        spinelt.remove();
      }
    });
  });


  // Click of Delete Profile
  $("#canprofilebtn").click(function(){
    $("#addprofilediv").css("display", "none");
    $("addprofileinp").val();
    $("#selectprofilediv").fadeIn();
  });


  // Click of Delete Profile
  $("#delprofilebtn").click(function(){
    // Callback object
    var pdata = new Object();
    pdata.fn = "deleteprofile";
    pdata.profileid = $('#selectprofile').val();

    // Provide feedback to the user
    var spinelt = createSpinner('selectprofilediv','');
    spinelt.css('top','10px');
    spinelt.css('left','100px');

    $.ajax('./vendor/app/php/app.php',
    {
      dataType: 'json',
      type: 'POST',
      data: pdata,
      success: function (data,status,xhr) {
        console.log(data);
        spinelt.remove();

        // If OK, refresh the profiles list & select the new one
        if (data.result == "ok") {
          // Refresh the Profiles Select
          BuildProfilesSelect(data.id);
        }
        else {
        }
      },
      error: function (jqXhr, textStatus, errorMessage) {
        console.log('Error: ' + errorMessage);
        spinelt.remove();
      }
    });
  });


  // On change of Select Schedule
  $("#selectschedule").change(function() {
    showScheduleElements();
  });


  // Update a settings value for the scheduling on leaving the field
  $(".scheduleupd").change(function(){
    // Separate JSON calls lock the database, so resolve this here...
    var scheduleOpts = new Array();

    // Save the Schedule Type
    var scheduleTypeObj = new Object();
    scheduleTypeObj.key = "selectschedule";
    scheduleTypeObj.val = $('#selectschedule').val();
    scheduleOpts.push(scheduleTypeObj);

    // Save the Schedule Options
    $("#settingsschedule"+$("#selectschedule").val()+"div").find(".scheduleupd").each(function(i, elt){
      var scheduleOpt = new Object();
      scheduleOpt.key = $(elt).attr("id");
      scheduleOpt.val = $(elt).val();
      scheduleOpts.push(scheduleOpt);
    })

    var pdata = new Object();
    pdata.fn = "updateschedule";
    pdata.profileid = $('#selectprofile').val();
    pdata.scheduleopt = JSON.stringify(scheduleOpts);

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
  });

  $("#settingsschedulemonthlydayselect").change(function(){
    setScheduleMonthlyDaySelectLabel();
  });

  Init();

}); // Page Ready

// ============================================================================================================================================

function clearIncludeExcludeItemsBrowse(type) {
  $("#"+type+"filescontainer").find("."+type+"item").remove();
}

function resetSettings() {
  // Clears all fields back to default settings
  // Inputs to blank

  $("#settingssection").find("input").each(function(i, elt) {
    elt = $(elt);
    switch (elt.attr('type')) {
      case "checkbox":
      case "radio":
        elt.prop("checked", false);
        break;
      default:
        elt.val("");
    }
  });

  clearIncludeExcludeItemsBrowse("include");
  clearIncludeExcludeItemsBrowse("exclude");
}

function setScheduleMonthlyDaySelectLabel() {
  var msg="";
  var labelelt = $("#daywarninglabel");
  switch($("#settingsschedulemonthlydayselect").val()) {
    case "31":
      msg="Warning: A snapshot will not be taken in February, April, June, September, and November!";
      break;
    case "30":
      msg="Warning: A snapshot will not be taken in February!";
      break;
    case "29":
      msg="Warning: A snapshot will not be taken in February, except in a Leap Year!";
      break;
    default:
      msg = "";
  }
  $("#daywarninglabel").text(msg);
  if (msg=="") labelelt.fadeOut();
  else labelelt.fadeIn();
}


function showScheduleElements() {
  // Hide all of the options
  $(".schedule").css("display", "none");
  // Show the correct option
  switch($("#selectschedule").val()){
    case "monthly":
      setScheduleMonthlyDaySelectLabel();
    case "minute":
    case "hour":
    case "daily":
    case "weekly":
      $("#settingsschedule"+$("#selectschedule").val()+"div").fadeIn();
      break;
    default:
      break;
      // nothing
  }
}


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

  // actiontype = 'include/exclude/show'
  // type       = 'd/-' (directory/file)
  // dir        = 'directory'
  // sel        = change to dir (or blank for current)

  var pdata = new Object();
  pdata.fn = "getdirectorycontents";
  pdata.type = type;
  pdata.dir = dir;
  pdata.sel = sel;
  pdata.hid = 1;
  
  // Correct showHidden for the front browse
  if (actiontype == "show") pdata.hid = showHidden

  // Get a description of what is being searched for
  var Bdesc = "Select "+getIncExcTypeDesc(type)+" to "+actiontype;
  $("#"+actiontype+"addfilebrowse").find("nav").text(Bdesc);

  // Ensure the body of the file browse is clear
  $("#"+actiontype+"filebrowsebody").find("div."+actiontype+"item").remove();

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
      
      if (data.result == "ok") {
        switch (actiontype) {
          case "include":
            includeFileBrowseDir = data.newdir;
            break;
          case "exclude":
            excludeFileBrowseDir = data.newdir;
            break;
          case "saveto":
            saveToFileBrowseDir = data.newdir;
            break;
          case "show":
            $("#filedirinput").val(data.newdir);
            break;
          default:
            console.log("Unknown actiontype: "+actiontype);
        }
              
        // Insert the item for 'Back'
        switch (actiontype) {
          case "include":
          case "exclude":
          case "saveto":
            // Fill in the new location
            $("#"+actiontype+"addfilelocation").text(data.newdir);

            // Create an item for the back/up button
            var BackItem = new Object();
            BackItem.id = "-1";
            BackItem.filedate = "";
            BackItem.filename = "..";
            BackItem.filesize = "";
            BackItem.filetype = "back";

            // Insert the folder up / back icon, and location
            createFileLine("back", actiontype, "-", BackItem, $("#"+actiontype+"filebrowsebody"));
            break;
          default:
            console.log("Unknown actiontype: "+actiontype);
        }

        // Draw the returned contents
        $.each(data.items, function (i, item) {
          createFileLine(i, actiontype, type, item, $("#"+actiontype+"filebrowsebody"));
        });
      }
      else {
        alert(data.message);
      }

    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);

      spinelt.remove();
    }
  });
}

function removeTriggerClass(tclass) {
  // Temporarily remove the 'tclass' class from the element
  var tclass_elts = $("."+tclass);
  tclass_elts.each(function(i, elt){
    $(elt).removeClass(tclass);
  });
  return tclass_elts;
}

function addTriggerClass(tclass_elts, tclass) {
  // Reinstate the 'scheduleupd' class to the element
  tclass_elts.each(function(i, elt){
    $(elt).addClass(tclass);
  });
}

// Draw the divs for the contents - used only for Show Browse in front screen
function createFileLine(i, actiontype, type, item, celt) {

  var iconName = getFileIcon(item.filetype);
  
  var stripeclass = "stripeodd";
  if (i % 2 == 0) stripeclass = "stripeeven";

  var rowelt = $("<div/>").addClass("row snaprow "+actiontype+"item "+stripeclass).attr("id","file_"+item.id).attr("filetype",item.filetype.toLowerCase()).attr("filename",item.filename);
  var fncelt = $("<span/>").attr("id","fnamecont_"+item.id).addClass("col-6 showitemfile").appendTo(rowelt);
  var imgelt = $("<span/>").addClass("iconify").attr("id","icon_"+item.id).attr("data-icon",iconName).appendTo(fncelt);
  var namelt = $("<span/>").attr("id","name_"+item.id).text(item.filename).appendTo(fncelt);
  
  var fdt = new Date(item.filedate);
  var datelt = $("<span/>").attr("id","date_"+item.id).text(fdt.toLocaleString()).addClass("col-3 showitemfile").appendTo(rowelt);
  
  var fty = "";
  switch (item.filetype) {
    case "d":
    case "back":
      fty = "Folder";
      break;
    case "l":
      fty = "Link";
      break;
    case "-":
      fty = "File";
      break;
  }
  var typelt = $("<span/>").attr("id","type_"+item.id).text(fty).addClass("col-1 showitemfile").appendTo(rowelt);

  var fsz = "";
  if (item.filetype == "-") fsz = item.filesize.toLocaleString();
  var sizelt = $("<span/>").attr("id","size_"+item.id).text(fsz).addClass("col-2 showitemfile").css("text-align","right").appendTo(rowelt);
  rowelt.appendTo(celt);

  // Add trigger
  rowelt.click(function() {
    // Get the id of the currently selected item using the class name
    var selid = $(this).attr("id");
    // Get all items in the area with the 'inclexclsel' class, and remove that class
    var elts = celt.find(".inclexclsel").removeClass("inclexclsel");
    // Set the value of the hidden selector, if it is not the same setid
    $(this).addClass("inclexclsel");

    switch (actiontype) {
      case "include":
      case "exclude":
      case "saveto":
        if (type == $(this).attr("filetype")) {
          $("#"+actiontype+"addselect").removeAttr("disabled");
        }
        else {
          $("#"+actiontype+"addselect").attr("disabled","disabled");
        }
        break;
      default:
        console.log("Unknown actiontype: "+actiontype);
    }
  });

  // Add trigger
  rowelt.dblclick(function() {
    // You can't drill down into a file
    if ($(this).attr("filetype") != "-") {
      // Callback to get the directory listing
      switch (actiontype) {
        case "include":
          getDirectoryContents(actiontype, type, includeFileBrowseDir, $(this).attr("filename"));
          break;
        case "exclude":
          getDirectoryContents(actiontype, type, excludeFileBrowseDir, $(this).attr("filename"));
          break;
        case "saveto":
          getDirectoryContents(actiontype, type, saveToFileBrowseDir, $(this).attr("filename"));
          break;
        case "show":
          getDirectoryContents(actiontype, type, $("#filedirinput").val(), $(this).attr("filename"));
          break;
        default:
          console.log("Unknown actiontype: "+actiontype);
      }
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

      // Remove current items here
      clearIncludeExcludeItemsBrowse("include");

      if (data.items.length > 0) {
        insertIncludeItems(data.items);
      }
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
    }
  });
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
      // Remove current items here
      clearIncludeExcludeItemsBrowse("exclude");

      if (data.items.length > 0) {
        insertExcludeItems(data.items);
      }
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
    }
  });
}


function insertIncludeExcludeRow(i, actiontype, item) {

  var iconName = getFileIcon(item.settype);

  var stripeclass = "stripeodd";
  if (i % 2 == 0) stripeclass = "stripeeven";

  var cdiv   = $("<div/>").attr("id",actiontype+"row_"+item.setid).addClass("row snaprow "+actiontype+"item "+stripeclass).attr("setid",item.setid);
  var imgelt = $("<span/>").addClass("iconify").attr("id","icon_"+item.setid).attr("data-icon",iconName).appendTo(cdiv);
  var namelt = $("<span/>").attr("id","name_"+item.setid).text(item.setval).appendTo(cdiv);
  cdiv.appendTo($("#"+actiontype+"filescontainer"));

  return cdiv;
}


function getFileIcon(ftype) {
  var iconName = "";
  switch (ftype.toLowerCase()) {
    case "d":
      iconName = "mdi-folder-outline";
      break;
    case "f":
    case "-":
      iconName = "mdi-file-outline";
      break;
    case "l":
      iconName = "mdi-file-link-outline";
      break;
    // Special icon for folder up
    case "back":
      iconName = "mdi-folder-upload";
      break;
    default:
      iconName = "mdi-file-question-outline";
      break;
  }

  return iconName;  
}


function Init() {

  var pdata = new Object();
  pdata.fn = "init";

  // Provide feedback to the user
  var spinelt = createSpinner('showfilebrowsebody','large');
  spinelt.css('top','50px');
  spinelt.css('left','350px');

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);

      // Get the Profiles select built
      BuildProfilesSelect("");

      spinelt.remove();
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
      spinelt.remove();
    }
  });

}


function BuildProfilesSelect(selId) {

  // Get the Profiles List for the DDL
  var pdata = new Object();
  pdata.fn = "selectprofileslist";
  pdata.profileid = $('#selectprofile').val();

  // Provide feedback to the user
  var spinelt = createSpinner('selectprofilediv','');
  spinelt.css('top','10px');
  spinelt.css('left','100px');

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
              text: item.profilename,
              candel: item.candelete,
              selected: item.selected
            }));
          } else {
            $('#selectprofile').append($('<option>', {
              value: item.id,
              text : item.profilename,
              candel: item.candelete
            }));
          }
        });
        // Trigger a change to initialise the settings
        $("#selectprofile").trigger('change');
        $('#selectprofile').addClass('autoupd');

        spinelt.remove();
      }
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
      spinelt.remove();
    }
  });

}


// Side Menu - list of Snapshots
function GetSideMenu(){

  // Get the Profiles List for the DDL
  var pdata = new Object();
  pdata.fn = "selectsnapshotslist";
  pdata.profileid = $('#selectprofile').val();

  // Provide feedback to the user
  var spinelt = createSpinner('snapshotssidemenu','large');
  spinelt.css('top','100px');
  spinelt.css('left','50px');

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);
      BuildSideMenu(data.items);
      spinelt.remove();
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
      spinelt.remove();
    }
  });
}


function BuildSideMenu(data) {

  // Remove all options from the menu (#snapshotssidemenu)
  $("#snapshotssidemenu").find("a").remove();
  $(".snapdisabled").attr("disabled","disabled");
  // Add the 'Now' menu
  $("<a/>").addClass("list-group-item list-group-item-action bg-light snapshotsmenu").attr("href","#").attr("id","snapshotsmenu0").attr("snapid","0").attr("snapdate","Now").text("Now").appendTo("#snapshotssidemenu");
  // Add the Snapshot entry menus
  if (data.length > 0) {
    $.each(data, function (i, item) {
      var sdate = new Date(item.snaptime);  // UTC from server
      var snapText = sdate.toLocaleString();
      if (item.snapdesc) { snapText = snapText + "<br/>" + item.snapdesc; }
      $("<a/>").addClass("list-group-item list-group-item-action bg-light snapshotsmenu").attr("href","#").attr("id","snapshotsmenu"+item.id).attr("snapid",item.id).attr("snapdesc",item.snapdesc).attr("snapdate",sdate.toLocaleString()).html(snapText).appendTo("#snapshotssidemenu");
    });
  }
  $(".snapshotsmenu").click(function(){
    item = $(this);
    // Ensure we're flicked to the Snapshots page
    $("#topmenusnapshots").trigger("click");
    // Set the snapshotid on the page, which triggers a callback to refresh the snapshot displays
    $("#snapshotid").val(item.attr("snapid"));
    $("#snapshotname").val(item.attr("snapdesc"));
    $("#snapshotname").attr("placeholder",item.attr("snapdate"));
    $("#snapshotname").removeAttr("readonly");
    
    if (item.attr("id") == "snapshotsmenu0") $("#snapshotname").val("").attr("readonly", "readonly"); 
    else $("#snapshotname").removeAttr("readonly");
  });
  $("#snapshotsmenu0").trigger("click");
}


$("#snapshotname").change(function(){
  // Callback to register the change of name
  var pdata = new Object();
  pdata.fn = "updatesnapshotname";
  pdata.snapshotid = $("#snapshotid").val();
  pdata.snapshotname = $("#snapshotname").val();

  // Provide feedback to the user
  var spinelt = createSpinner('snapshotssidemenu','large');
  spinelt.css('top','100px');
  spinelt.css('left','50px');

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);
      spinelt.remove();
      if (data.result == "ok") BuildSideMenu(data.snaplist.items);
      else alert(data.message);
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
      spinelt.remove();
    }
  });
});


// Top Menu
$("#topmenusnapshots").click(function(){
  $('.contentsection').css('display','none');
  $('#snapshotsection').fadeIn();
});
$("#topmenusettings").click(function(){
  $('.contentsection').css('display','none');
  $('#settingssection').fadeIn();
});
$("#topmenulogs").click(function(){
  $('.contentsection').css('display','none');
  $('#logssection').fadeIn();
});
$("#topmenuabout").click(function(){
  $('.contentsection').css('display','none');
  $('#aboutsection').fadeIn();
});


// Snapshot Toolbar
// Take Snapshot
$("#snapshotnewbtn").click(function(){

  // Request a new snapshot to be taken
  var pdata = new Object();
  pdata.fn = "takenewsnapshot";
  pdata.profileid = $('#selectprofile').val();

  // Provide feedback to the user
  var spinelt = createSpinner('snapshotssidemenu','large');
  spinelt.css('top','100px');
  spinelt.css('left','50px');

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);
      spinelt.remove();

      if (data.result == "ok") BuildSideMenu(data.snaplist.items);
      else alert(data.message);
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
      spinelt.remove();
    }
  });
});


// Refresh Snapshots List
$("#snapshotrefreshbtn").click(function(){
  GetSideMenu();
});


// Remove Snapshot
$("#snapshotremovebtn").click(function(){
  // Request a new snapshot to be taken
  var pdata = new Object();
  pdata.fn = "deletesnapshot";
  pdata.snapshotid = $('#snapshotid').val();

  // Provide feedback to the user
  var spinelt = createSpinner('snapshotssidemenu','large');
  spinelt.css('top','100px');
  spinelt.css('left','50px');

  $.ajax('./vendor/app/php/app.php',
  {
    dataType: 'json',
    type: 'POST',
    data: pdata,
    success: function (data,status,xhr) {
      console.log(data);
      spinelt.remove();

      if (data.result == "ok") BuildSideMenu(data.snaplist.items);
      else alert(data.message);
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
      spinelt.remove();
    }
  });
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
  $("#topmenusettings").trigger("click");
});
// Help
$("#snapshothelpbtn").click(function(){
  alert('Help');
});


// Files Toolbar
// Up
$("#fileupbtn").click(function(){
  getDirectoryContents("show", "-", $("#filedirinput").val(), "..");
});
// Toggle hidden files
$("#filetogglehiddenbtn").click(function(){
  if (showHidden == 0) showHidden = 1; else showHidden = 0;
  getDirectoryContents("show", "-", $("#filedirinput").val(), "");
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

$("#globalroot").click(function(){
  // Remove inclexclsel class if set, and set to this
  clearSnapShortcutsSel();
  $(this).addClass("inclexclsel");
  $("#filedirinput").val("/").trigger("change");
});

$("#globalshares").click(function(){
  // Remove inclexclsel class if set, and set to this
  clearSnapShortcutsSel();
  $(this).addClass("inclexclsel");
  $("#filedirinput").val("/shares").trigger("change");
});

function clearSnapShortcutsSel(){
  $("#shortcutscontainer").find(".inclexclsel").each(function (i, elt){
    $(elt).removeClass("inclexclsel");
  });
  $("#snapdirsfilescontainer").find(".inclexclsel").each(function(i, elt){
    $(elt).removeClass("inclexclsel");
  });  
}

$("#snapshotid").change(function(){
  // Callback to get the snapshot paths and contents
  console.log('Snapshot path for snapshot id: '+$("#snapshotid").val());
});

$("#filedirinput").change(function(){
  var items = getDirectoryContents("show", "-", $("#filedirinput").val(), "");
});

$("#snapshotsheadingnav").click(function(){
  $("#topmenusnapshots").trigger("click");
});


$("#savetobutton").click(function(){
  // Remove the includefilescontainer
  $("#settings-general-main").css("display","none");
  // Ensure the body of the file browse is clear
  $("#savetobrowsebody").empty();
  // Fade in the includeaddfilebrowse
  $("#savetofilebrowse").fadeIn();

  saveToFileBrowseDir = $("#settingssaveto").val();
  getDirectoryContents("saveto", saveToType, saveToFileBrowseDir, "");
});

$("#savetocancel").click(function(){
  // Remove the includefilescontainer
  $("#settingssaveto").val(saveToFileBrowseDir);
  // Hide the browse
  $("#savetofilebrowse").css("display","none");
  // Ensure the body of the file browse is clear
  $("#savetobrowsebody").empty();
  // Fade in the settings-general-main
  $("#settings-general-main").fadeIn();
});

$("#savetoselect").click(function(){
  // Remove the includefilescontainer
  var selFile = saveToFileBrowseDir + "/" + $("#savetofilebrowsebody").find(".inclexclsel").attr("filename");
  // Set the input to the chosen one
  $("#settingssaveto").val(selFile);
  $("#settingssaveto").trigger("change");
  // Remove the includefilescontainer
  $("#savetofilebrowse").css("display","none");
  // Ensure the body of the file browse is clear
  $("#savetobrowsebody").empty();
  // Fade in the settings-general-main
  $("#settings-general-main").fadeIn();
});


// Click of Select in Include Files Browse
$("#includeaddselect").click(function(){
  var selFile = includeFileBrowseDir + "/" + $("#includeaddfilebrowse").find(".inclexclsel").attr("filename");
  var selType = $("#includeaddfilebrowse").find(".inclexclsel").attr("filetype");
  $("#includeaddfilebrowse").css("display","none");
  $("#includefilescontainer").fadeIn();
  $("#includefilesbuttons").fadeIn();

  // Provide feedback to the user
  var spinelt = createSpinner('includefilescontainer','');
  spinelt.css('top','5px');
  spinelt.css('left','190px');

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
      spinelt.remove();

      // If OK, refresh the include list
      getIncludeItems();
    },
    error: function (jqXhr, textStatus, errorMessage) {
      console.log('Error: ' + errorMessage);
      spinelt.remove();
    }
  });

});


function insertIncludeItems(data) {
  
  // Include Files/Folders
  // Remove current items
  
  if (data.length > 0) {
    // Remove current items here
    clearIncludeExcludeItemsBrowse("include");

    $.each(data, function (i, item) {
      var actiontype = "include";
      
      // Show included items in the frontend
      var cdiv = insertIncludeExcludeRow(i, actiontype, item);

      // Add trigger
      $(cdiv).click(function() {
        // Get the id of the currently selected item using the class name
        var selid = $("#"+actiontype+"filescontainer").find(".inclexclsel").attr("id");
        // Get all items in the area with the 'inclexclsel' class, and remove that class
        var elts = $("#"+actiontype+"filescontainer").find(".inclexclsel").removeClass("inclexclsel");
        // Blank the hidden input
        $("#this"+actiontype+"setid").val("");
        // Disable the remove button
        $("#"+actiontype+"remove").attr("disabled","disabled");
        // Set the value of the hidden selector, if it is not the same setid
        if (selid != $(this).attr("id")) {
          $("#this"+actiontype+"setid").val($(this).attr("id"));
          $(this).addClass("inclexclsel");
          $("#"+actiontype+"remove").removeAttr("disabled");
        }
      });
    });
    
    // Remove current items here
    clearIncludeExcludeItemsBrowse("snapdirs");
    
    // Round the loop again, for the Include Directories in the Snapshots screen
    var c = 0;
    $.each(data, function (i, item) {
      var actiontype = "snapdirs";

      // Also add the include folders to the Snapshots 'Backup Folders' list
      if (item.settype == 'd') {
        var cdiv = insertIncludeExcludeRow(c, actiontype, item)
        c = c + 1;
        // Add trigger
        $(cdiv).click(function() {
          // Get the id of the currently selected item using the class name
          var selid = $("#"+actiontype+"filescontainer").find(".inclexclsel").attr("id");
          // Get all items in the area with the 'inclexclsel' class, and remove that class
          clearSnapShortcutsSel();

          // Set the value of the file browse bar, if it is a folder, and trigger a refresh of the files area
          if (item.settype == "d") {
            $("#filedirinput").val(item.setval);
            $(this).addClass("inclexclsel");
            $("#filedirinput").trigger("change");
          }
        });
      }
    });
  }
}

function insertExcludeItems(data) {
  // Exclude Files/Folders
  // Remove current items
  if (data.length > 0) {
    // Remove current items here
    clearIncludeExcludeItemsBrowse("exclude");

    $.each(data, function (i, item) {

      // Show excluded items in the frontend
      var cdiv = insertIncludeExcludeRow(i, 'exclude', item);

      // Add trigger
      $(cdiv).click(function() {
        // Get the id of the currently selected item using the class name
        var selid = $("#excludefilescontainer").find(".inclexclsel").attr("id");
        // Get all items in the area with the 'inclexclsel' class, and remove that class
        var elts = $("#excludefilescontainer").find(".inclexclsel").removeClass("inclexclsel");
        // Blank the hidden input
        $("#thisexcludesetid").val('');
        // Disable the remove button
        $("#excluderemove").attr("disabled","disabled");
        // Set the value of the hidden selector, if it is not the same setid
        if (selid != $(this).attr("id")) {
          $("#thisexcludesetid").val($(this).attr("id"));
          $(this).addClass("inclexclsel");
          $("#excluderemove").removeAttr("disabled");
        }
      });

    });
  }  
}
