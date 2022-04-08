import React from "react";

const Control = ( props ) => {
  const { label, description, children } = props;

  return (
    <div className="sm-control">
      { label &&
        <div className="sm-control__header">
          <div className="sm-control__label">{ label }</div>
        </div>
      }
      { children && <div className="sm-control__body">{ children }</div> }

      { description &&
        <div className="sm-control__footer">
          <div className="description customize-control-description sm-control__description" dangerouslySetInnerHTML={{ __html: description }}></div>
        </div>
      }
    </div>
  )
};

export default Control;
