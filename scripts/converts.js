const path = require('path');
const testFolder = path.join( __dirname +'/../files/');
const destination = path.join( __dirname +'/../components/');
const fs = require('fs');

var ctrlJson,
  cpName = "",
  json,
  template,
  script,
  partScript,
  style,
  component ="",
  count = 0,
  cmps="";

fs.readdirSync(testFolder).forEach((element)=>{
  ctrlJson =0,
  count = 0;
  template = "<template></template>";
  script = "<script></script>";
  style = "<style></style>";

  cpName = path.basename( element );
  element = path.join( __dirname +'/../files/' + cpName);

  if( fs.lstatSync(element).isDirectory() ){
    let notice = false;
    script= "<script></script>";
    var subDirectory = element;
    let filesChecks = {
      html: path.basename( subDirectory ) + '.html',
      js : path.basename( subDirectory ) + '.js',
      style : path.basename( subDirectory ) + '.less',
      json : element+"/bbn.json"
    };
//if exist json
    if ( fs.existsSync( filesChecks.json ) ){
      let packageJson = JSON.parse( fs.readFileSync( path.join( __dirname +'/../package.json' ) ) );
      packageJson = packageJson.dependencies;
      let requireLibrary ="";
      let nameRequire;
      let list_require={};
      let ctrl_require = 0;
      let versionRequire;
      json = JSON.parse( fs.readFileSync( filesChecks.json ) );
      json = json.dependencies;
      Object.keys(json).forEach( (key)=>{
        requireLibrary += "var " + key.toString() + " =  require('" + key.toString() + "');\n";
        nameRequire=  key.toString();
        versionRequire = json[key];
        ctrl_require = false;
        Object.keys(packageJson).forEach( (key)=>{
          if( key.toString() === nameRequire ){
            ctrl_require = true;
          }
        });
        if( !ctrl_require ){
          list_require[nameRequire] = versionRequire;
          console.log("-----------------------------------------!!NOTICE!!------------------------------------------------");
          console.log("create bbn-" + cpName + " but the component required dependencies: ");
          console.log(list_require);
          console.log("---------------------------------------------------------------------------------------------------");
          notice= true;
        }

      });

      partScript= "<script>\n" + "  " + requireLibrary;
      ctrlJson = 1;
    }
    fs.readdirSync(subDirectory).forEach( (file)=>{
      count ++;
    });
    //for component with more files
    if ( count==2 || count > 2 ){
      fs.readdirSync(subDirectory).forEach( (file)=>{
        let fullScript = fs.readFileSync(subDirectory + '/' + file).toString();
        if ( filesChecks.html === file ){
          template  = "<template>"+"\n"+fs.readFileSync(subDirectory + '/' + file).toString()+"\n"+"</template>";
        }/*else if( fullScript.lastIndexOf( "template:" ) != -1 ){
          template= "<template>" + fullScript.substring( fullScript.indexOf("template:"), fullScript.indexOf('props:') ) + "</template>\n";
        }*/
        if ( filesChecks.js === file ){
          if(!ctrlJson){
            partScript ="\n<script>";

          }


          partScript = partScript + fullScript.substring( fullScript.lastIndexOf("use strict"), fullScript.indexOf('Vue') ).replace( 'use strict";', '');

          partScript = partScript + 'export default {\n' +
           '    name:' + "'bbn-" + path.basename( subDirectory )+ "'"+ ',';

          if( fullScript.indexOf( " mixins:" ) != -1 ){
            partScript = partScript + "\n" + fullScript.substring( fullScript.indexOf("mixins:"), fullScript.indexOf('props:') );
          };
          let restScript;
          restScript = fullScript.slice( fullScript.indexOf("props:"), fullScript.lastIndexOf("});") );
          script = partScript + "\n    "+restScript +  "}\n</script>";

        }
        if ( filesChecks.style === file ){
          style  = "<style>\n"+fs.readFileSync(subDirectory + '/' + file).toString()+"\n</style>";
        }
      });
      component = template + "\n"+ script + "\n" +style;
      //-template
      /*if( component.lastIndexOf(" template:" ) != -1 ){
        console.log("ciccio", component.substring( component.lastIndexOf("template:"), component.indexOf(',') ));
        //component = component.replace( , " " ) + "\n";
      };*/

      fs.writeFile( destination + "bbn-" + cpName + ".vue", component, function(err) {
        if(err) {
          console.log(err);
          return
        }
      });
  //for component with single file
    }else{
      fs.readdir(subDirectory, function(err, files){
        files.map(function (file) {
          return path.join(subDirectory, file);
        }).filter(function (file) {
            return fs.statSync(file).isFile();
          }).forEach(function (file) {
            if (path.extname(file) === ".js"){
              var base = path.basename(file);
              base = base.replace(".js", "");
              //elaborate for create component single file
              covertition( file,  "bbn-" + base  );
            }

          });
      });
    }
    let cmp ="Vue.component('bbn-" + path.basename( subDirectory ) + "'," + "require('../components/bbn-" + cpName +  ".vue') );" ;
    cmps = cmps + "\n" + cmp;
    if (!notice){
      let name= "create bbn-"+path.basename( subDirectory );
      console.log(name);
    }
  };
});

fs.writeFile( path.join( __dirname +'/../core-components/all-components.js'), cmps.toString(), function(err) {
   if(err) {
     return console.log(err);
   }
 });
console.log();
console.log("SAVED COMPONENTS");


//function elaborate and create cponent
var covertition = ( file , nameComp ) =>{
  //check if the file exists
   if ( fs.existsSync( file ) ){
    // se esiste mi calcolo la destinazione della scrittura
      fs.readFile(file, function(error, content) {

      //There are no read-only errors in the process of splitting and writing vue component files

      //operation for template
       template = "<template></template>";
       let code = content.toString();
       let restScript;

       if( code.indexOf( "<" ) != -1 ){
         template = '<template>\n' + code.substring( code.indexOf("<"), code.indexOf('props:') ) + '</template>\n';
       //  template = template.replace("' +'", "");
         template = template.replace("',", "");
       }

       let partComponent = template +
        '<script>\n' +
        ' export default {\n' +
        '  name:' + "'" + nameComp + "'"+ ',\n';
      //CHECK mixins if is true insert

       if( code.indexOf( "[" ) != -1 ){
         partComponent += '\n  mixins:' + code.substring( code.indexOf( "[" ), code.indexOf(']') ) + "],\n";
       }
       restScript = code.slice( code.indexOf("  props:"), code.lastIndexOf("});") );
       component = partComponent+restScript+  "}\n</script>\n<style>\n</style>";
       //FOR ADD COMPONENT MODULE

       fs.writeFile( destination + "/"+ nameComp + ".vue", component, function(err) {
         if(err) {
           console.log(err);
           return
         }
       });
    });
  }
  else{
    console.log("No found file");
  }
};
